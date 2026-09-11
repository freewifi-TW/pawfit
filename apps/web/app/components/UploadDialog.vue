<script setup lang="ts">
import type { Media, MediaKind, Visibility } from '~/types/api'
import { formatBytes } from '~/utils/labels'

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
const { t } = useI18n()
const { kindLabelLong, visibilityLabel } = useLabels()

const KINDS: MediaKind[] = ['art2d', 'model3d', 'photo']
const VISIBILITIES: Visibility[] = ['public', 'unlisted', 'private']

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
      notify.err(t('media.upload.unsupportedFormat', { name: f.name }), t('media.upload.unsupportedFormatHint'))
      continue
    }
    if (f.size > props.maxBytes) {
      notify.err(t('media.upload.tooLarge', { name: f.name }), t('media.upload.tooLargeHint', { size: formatBytes(props.maxBytes) }))
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
    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300 ? resolve() : reject(new Error(t('media.upload.putFailedStatus', { status: xhr.status }))))
    xhr.onerror = () => reject(new Error(t('media.upload.putFailedNetwork')))
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
    // 直傳（XHR）丟出的是沒有 HTTP response 的一般 Error，直接顯示它自己的訊息
    item.error = err.status === 0 && e instanceof Error ? e.message : err.message
    if (storageKey) {
      api('/media/abandon', { method: 'POST', body: { fursona_id: props.fursonaId, storage_key: storageKey } }).catch(() => {})
    }
    throw e
  }
}

async function submit() {
  errors.value = {}
  if (!items.value.length) errors.value.files = t('media.upload.errors.files')
  if (!kind.value) errors.value.kind = t('media.upload.errors.kind')
  if (nsfw.value === null) errors.value.nsfw = t('media.upload.errors.nsfw')
  if (creditUrl.value && !/^https?:\/\//i.test(creditUrl.value)) errors.value.credit_url = t('media.upload.errors.creditUrl')
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
    notify.ok(t('media.upload.queued'), t('media.upload.queuedHint'))
    reset()
    open.value = false
  } else {
    notify.err(t('media.upload.failedCount', failed), t('media.upload.failedHint'))
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
    :title="t('media.upload.title')"
    :description="t('media.upload.description')"
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
      <b>{{ t('media.upload.dropTitle') }}</b>
      {{ t('media.upload.dropHint', { size: formatBytes(maxBytes) }) }}
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
            {{ t('media.upload.uploadedProcessing') }}
          </div>
        </div>
        <button
          v-if="it.status !== 'uploading' && it.status !== 'done'"
          class="btn sm ghost"
          type="button"
          @click="remove(i)"
        >
          {{ t('media.upload.remove') }}
        </button>
      </div>
    </div>

    <div class="field">
      <span class="lbl">{{ t('media.upload.kind') }} <span
        class="pill danger"
        style="font-size:10px"
      >{{ t('media.upload.required') }}</span></span>
      <div class="seg">
        <label
          v-for="k in KINDS"
          :key="k"
        >
          <input
            v-model="kind"
            type="radio"
            name="upload-kind"
            :value="k"
          >{{ kindLabelLong(k) }}
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
      <span class="lbl">{{ t('media.upload.rating') }} <span
        class="pill danger"
        style="font-size:10px"
      >{{ t('media.upload.required') }}</span></span>
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
        {{ t('media.upload.ratingHint') }}
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
        <label for="up-credit">{{ t('media.upload.credit') }}</label>
        <input
          id="up-credit"
          v-model="creditName"
          class="input"
          maxlength="80"
          :placeholder="t('media.upload.creditPlaceholder')"
        >
      </div>
      <div
        class="field"
        style="margin:0"
      >
        <label for="up-credit-url">{{ t('media.upload.creditUrl') }}</label>
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
      <label for="up-caption">{{ t('media.upload.caption') }}</label>
      <input
        id="up-caption"
        v-model="caption"
        class="input"
        maxlength="200"
        :placeholder="t('media.upload.captionPlaceholder')"
      >
      <div
        v-if="items.length > 1"
        class="hint"
      >
        {{ t('media.upload.captionMultiHint') }}
      </div>
    </div>
    <div
      class="field"
      style="margin:0"
    >
      <label for="up-vis">{{ t('media.upload.visibility') }}</label>
      <select
        id="up-vis"
        v-model="override"
        class="input"
      >
        <option value="">
          {{ t('media.upload.inherit', { visibility: visibilityLabel(fursonaVisibility) }) }}
        </option>
        <option
          v-for="v in VISIBILITIES"
          :key="v"
          :value="v"
        >
          {{ visibilityLabel(v) }}
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
        {{ t('media.common.cancel') }}
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="submit"
      >
        {{ busy ? t('media.upload.uploading') : (items.length ? t('media.upload.submitCount', { n: items.length }) : t('media.upload.submit')) }}
      </button>
    </template>
  </PawDialog>
</template>
