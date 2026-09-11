<script setup lang="ts">
import type { Media, MediaKind, Visibility } from '~/types/api'
import { formatBytes, KIND_LABEL_LONG, VISIBILITY_LABEL } from '~/utils/labels'

/**
 * 上傳流程（SASD §2.1）：presign → 瀏覽器 PUT 直傳原檔 → confirm 寫 DB → 後端 queue 產衍生版。
 * 分類與分級為必填，不可略過（FR-3.2、FR-3.4）。
 */
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{
  fursonaId: string
  fursonaVisibility: Visibility
  maxBytes: number
}>()
const emit = defineEmits<{ uploaded: [media: Media] }>()

const api = useApi()
const notify = useNotify()

interface Item {
  file: File
  preview: string
  progress: number
  status: 'pending' | 'uploading' | 'done' | 'error'
  error?: string
}

const items = ref<Item[]>([])
const kind = ref<MediaKind | null>(null)
const nsfw = ref<boolean | null>(null)
const creditName = ref('')
const creditUrl = ref('')
const caption = ref('')
const override = ref<Visibility | ''>('')
const busy = ref(false)
const over = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const errors = ref<Record<string, string>>({})

const ACCEPT = ['image/jpeg', 'image/png', 'image/webp']

function addFiles(list: FileList | File[]) {
  for (const f of Array.from(list)) {
    if (!ACCEPT.includes(f.type)) {
      notify.err(`${f.name} 不是支援的格式`, '只接受 jpg / png / webp。')
      continue
    }
    if (f.size > props.maxBytes) {
      notify.err(`${f.name} 太大`, `單檔上限 ${formatBytes(props.maxBytes)}。`)
      continue
    }
    items.value.push({ file: f, preview: URL.createObjectURL(f), progress: 0, status: 'pending' })
  }
}

function onDrop(e: DragEvent) {
  over.value = false
  if (e.dataTransfer?.files) addFiles(e.dataTransfer.files)
}

function remove(i: number) {
  URL.revokeObjectURL(items.value[i]!.preview)
  items.value.splice(i, 1)
}

function putWithProgress(url: string, headers: Record<string, string>, file: File, onProgress: (p: number) => void) {
  return new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', url)
    for (const [k, v] of Object.entries(headers)) {
      if (k.toLowerCase() !== 'host' && k.toLowerCase() !== 'content-length') xhr.setRequestHeader(k, v)
    }
    if (!Object.keys(headers).some(k => k.toLowerCase() === 'content-type')) {
      xhr.setRequestHeader('Content-Type', file.type)
    }
    xhr.upload.onprogress = e => e.lengthComputable && onProgress(Math.round((e.loaded / e.total) * 100))
    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(`直傳失敗（${xhr.status}）`)))
    xhr.onerror = () => reject(new Error('直傳失敗，請確認網路或儲存服務的 CORS 設定'))
    xhr.send(file)
  })
}

async function uploadOne(item: Item) {
  item.status = 'uploading'
  let storageKey: string | null = null
  try {
    const presign = await api<{ storage_key: string, upload_url: string, headers: Record<string, string> }>('/media/presign', {
      method: 'POST',
      body: { fursona_id: props.fursonaId, content_type: item.file.type, bytes: item.file.size }
    })
    storageKey = presign.storage_key
    await putWithProgress(presign.upload_url, presign.headers ?? {}, item.file, p => (item.progress = p))

    const media = await api<Media>('/media/confirm', {
      method: 'POST',
      body: {
        fursona_id: props.fursonaId,
        storage_key: storageKey,
        kind: kind.value,
        is_nsfw: nsfw.value,
        caption: items.value.length === 1 ? (caption.value || null) : (caption.value ? `${caption.value}` : null),
        credit_name: creditName.value || null,
        credit_url: creditUrl.value || null,
        visibility_override: override.value || null
      }
    })
    item.status = 'done'
    item.progress = 100
    emit('uploaded', media)
  } catch (e) {
    item.status = 'error'
    const err = apiError(e)
    item.error = err.message === '發生錯誤，請再試一次。' && e instanceof Error ? e.message : err.message
    if (storageKey) {
      api('/media/abandon', { method: 'POST', body: { fursona_id: props.fursonaId, storage_key: storageKey } }).catch(() => {})
    }
    throw e
  }
}

async function submit() {
  errors.value = {}
  if (!items.value.length) errors.value.files = '請先選擇圖片。'
  if (!kind.value) errors.value.kind = '請選擇分類。'
  if (nsfw.value === null) errors.value.nsfw = '請標記內容分級。'
  if (creditUrl.value && !/^https?:\/\//i.test(creditUrl.value)) errors.value.credit_url = '連結需以 http(s):// 開頭。'
  if (Object.keys(errors.value).length) return

  busy.value = true
  let failed = 0
  for (const item of items.value.filter(i => i.status !== 'done')) {
    try {
      await uploadOne(item)
    } catch {
      failed++
    }
  }
  busy.value = false

  if (failed === 0) {
    notify.ok('已加入處理佇列', '展示版與縮圖產生完成後會自動出現在圖庫。')
    reset()
    open.value = false
  } else {
    notify.err(`${failed} 張上傳失敗`, '可以修正後再按一次上傳，成功的不會重傳。')
  }
}

function reset() {
  items.value.forEach(i => URL.revokeObjectURL(i.preview))
  items.value = []
  kind.value = null
  nsfw.value = null
  creditName.value = ''
  creditUrl.value = ''
  caption.value = ''
  override.value = ''
  errors.value = {}
}

watch(open, (v) => {
  if (!v && !busy.value) reset()
})
</script>

<template>
  <PawDialog
    v-model:open="open"
    title="上傳圖片"
    description="原檔會直接上傳到儲存空間，展示版與縮圖稍後自動產生。"
    wide
  >
    <div
      class="drop"
      :class="{ over }"
      role="button"
      tabindex="0"
      @click="fileInput?.click()"
      @keydown.enter.prevent="fileInput?.click()"
      @dragover.prevent="over = true"
      @dragleave="over = false"
      @drop.prevent="onDrop"
    >
      <b>拖曳圖片到這裡，或點擊選擇</b>
      jpg / png / webp，單檔 {{ formatBytes(maxBytes) }} 以內，可一次選多張。
      <input
        ref="fileInput"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        hidden
        @change="addFiles(($event.target as HTMLInputElement).files!); ($event.target as HTMLInputElement).value = ''"
      >
    </div>
    <p
      v-if="errors.files"
      class="err"
      style="margin:-8px 0 12px;font-size:12px;color:var(--danger);font-weight:700"
    >
      {{ errors.files }}
    </p>

    <div
      v-if="items.length"
      class="uplist"
    >
      <div
        v-for="(it, i) in items"
        :key="it.preview"
        class="uprow"
      >
        <img
          :src="it.preview"
          alt=""
        >
        <div>
          <div style="font-weight:700">
            {{ it.file.name }} <span class="muted">· {{ formatBytes(it.file.size) }}</span>
          </div>
          <div
            v-if="it.status === 'uploading'"
            class="bar"
            style="height:6px;margin-top:4px"
          >
            <i :style="`width:${it.progress}%`" />
          </div>
          <div
            v-else-if="it.status === 'error'"
            style="color:var(--danger)"
          >
            {{ it.error }}
          </div>
          <div
            v-else-if="it.status === 'done'"
            style="color:var(--ok)"
          >
            已上傳，處理中
          </div>
        </div>
        <button
          v-if="it.status !== 'uploading' && it.status !== 'done'"
          class="btn sm ghost"
          type="button"
          @click="remove(i)"
        >
          移除
        </button>
      </div>
    </div>

    <div class="field">
      <span class="lbl">分類 <span
        class="pill danger"
        style="font-size:10px"
      >必填</span></span>
      <div class="seg">
        <label
          v-for="(label, k) in KIND_LABEL_LONG"
          :key="k"
        >
          <input
            v-model="kind"
            type="radio"
            name="upload-kind"
            :value="k"
          >{{ label }}
        </label>
      </div>
      <div
        v-if="errors.kind"
        class="err"
      >
        {{ errors.kind }}
      </div>
    </div>

    <div class="field">
      <span class="lbl">內容分級 <span
        class="pill danger"
        style="font-size:10px"
      >必填</span></span>
      <div class="seg">
        <label><input
          v-model="nsfw"
          type="radio"
          name="upload-nsfw"
          :value="false"
        >SFW</label>
        <label><input
          v-model="nsfw"
          type="radio"
          name="upload-nsfw"
          :value="true"
        >NSFW</label>
      </div>
      <div class="hint">
        不可略過。未標記的 NSFW 內容經檢舉會被改標或下架。
      </div>
      <div
        v-if="errors.nsfw"
        class="err"
      >
        {{ errors.nsfw }}
      </div>
    </div>

    <div
      class="ed-grid"
      style="gap:14px"
    >
      <div
        class="field"
        style="margin:0"
      >
        <label for="up-credit">繪師 / 製作者</label>
        <input
          id="up-credit"
          v-model="creditName"
          class="input"
          maxlength="80"
          placeholder="例如 @kuro_lines"
        >
      </div>
      <div
        class="field"
        style="margin:0"
      >
        <label for="up-credit-url">作者連結（選填）</label>
        <input
          id="up-credit-url"
          v-model="creditUrl"
          class="input"
          :class="{ 'is-invalid': errors.credit_url }"
          maxlength="300"
          placeholder="https://"
        >
        <div
          v-if="errors.credit_url"
          class="err"
        >
          {{ errors.credit_url }}
        </div>
      </div>
    </div>
    <div
      class="field"
      style="margin-top:14px"
    >
      <label for="up-caption">說明文字</label>
      <input
        id="up-caption"
        v-model="caption"
        class="input"
        maxlength="200"
        placeholder="例如：正面設定圖，2026 年版"
      >
      <div
        v-if="items.length > 1"
        class="hint"
      >
        多張同時上傳時會套用同一段說明，之後可逐張編輯。
      </div>
    </div>
    <div
      class="field"
      style="margin:0"
    >
      <label for="up-vis">這張圖的隱私</label>
      <select
        id="up-vis"
        v-model="override"
        class="input"
      >
        <option value="">
          繼承獸設設定（{{ VISIBILITY_LABEL[fursonaVisibility] }}）
        </option>
        <option
          v-for="(label, v) in VISIBILITY_LABEL"
          :key="v"
          :value="v"
        >
          {{ label }}
        </option>
      </select>
    </div>

    <template #footer>
      <button
        class="btn"
        type="button"
        :disabled="busy"
        @click="open = false"
      >
        取消
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="submit"
      >
        {{ busy ? '上傳中…' : `上傳${items.length ? ` ${items.length} 張` : ''}` }}
      </button>
    </template>
  </PawDialog>
</template>
