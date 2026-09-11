<script setup lang="ts">
import type { Media, MediaKind, Visibility } from '~/types/api'
import { KIND_LABEL_LONG, VISIBILITY_LABEL } from '~/utils/labels'

/** 單張圖的編輯：說明、credit、分類、分級、隱私覆寫、設為頭像、刪除。 */
const media = defineModel<Media | null>({ default: null })
defineProps<{ fursonaVisibility: Visibility, isAvatar: boolean }>()
const emit = defineEmits<{ updated: [media: Media], deleted: [id: string], avatar: [id: string | null] }>()

const api = useApi()
const notify = useNotify()

const form = reactive({
  caption: '',
  credit_name: '',
  credit_url: '',
  kind: 'art2d' as MediaKind,
  is_nsfw: false,
  visibility_override: '' as Visibility | ''
})
const busy = ref(false)
const errors = ref<Record<string, string>>({})
const confirmDelete = ref(false)

const open = computed({
  get: () => media.value !== null,
  set: (v) => {
    if (!v) media.value = null
  }
})

watch(media, (m) => {
  confirmDelete.value = false
  errors.value = {}
  if (!m) return
  form.caption = m.caption ?? ''
  form.credit_name = m.credit_name ?? ''
  form.credit_url = m.credit_url ?? ''
  form.kind = m.kind
  form.is_nsfw = m.is_nsfw
  form.visibility_override = m.visibility_override ?? ''
})

async function save() {
  if (!media.value) return
  busy.value = true
  errors.value = {}
  try {
    const updated = await api<Media>(`/media/${media.value.id}`, {
      method: 'PATCH',
      body: {
        caption: form.caption || null,
        credit_name: form.credit_name || null,
        credit_url: form.credit_url || null,
        kind: form.kind,
        is_nsfw: form.is_nsfw,
        visibility_override: form.visibility_override || null
      }
    })
    emit('updated', updated)
    notify.ok('已儲存')
    media.value = null
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    notify.err('儲存失敗', err.message)
  } finally {
    busy.value = false
  }
}

async function remove() {
  if (!media.value) return
  busy.value = true
  try {
    await api(`/media/${media.value.id}`, { method: 'DELETE' })
    emit('deleted', media.value.id)
    notify.ok('已刪除圖片')
    media.value = null
  } catch (e) {
    notify.err('刪除失敗', apiError(e).message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PawDialog
    v-model:open="open"
    title="編輯圖片"
    :description="media?.status === 'processing' ? '這張圖還在處理中，仍可先編輯資料。' : ''"
  >
    <template v-if="media">
      <div
        class="row"
        style="align-items:flex-start;gap:14px;margin-bottom:14px"
      >
        <img
          v-if="media.status === 'active'"
          :src="media.urls.thumb"
          alt=""
          style="width:96px;height:96px;object-fit:cover;border-radius:12px;border:2px solid var(--line)"
        >
        <div
          class="field"
          style="flex:1;margin:0"
        >
          <label for="me-caption">說明文字</label>
          <input
            id="me-caption"
            v-model="form.caption"
            class="input"
            maxlength="200"
          >
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
          <label for="me-credit">繪師 / 製作者</label>
          <input
            id="me-credit"
            v-model="form.credit_name"
            class="input"
            maxlength="80"
          >
        </div>
        <div
          class="field"
          style="margin:0"
        >
          <label for="me-credit-url">作者連結</label>
          <input
            id="me-credit-url"
            v-model="form.credit_url"
            class="input"
            :class="{ 'is-invalid': errors.credit_url }"
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
        <span class="lbl">分類</span>
        <div class="seg">
          <label
            v-for="(label, k) in KIND_LABEL_LONG"
            :key="k"
          >
            <input
              v-model="form.kind"
              type="radio"
              name="me-kind"
              :value="k"
            >{{ label }}
          </label>
        </div>
      </div>
      <div class="field">
        <span class="lbl">內容分級</span>
        <div class="seg">
          <label><input
            v-model="form.is_nsfw"
            type="radio"
            name="me-nsfw"
            :value="false"
          >SFW</label>
          <label><input
            v-model="form.is_nsfw"
            type="radio"
            name="me-nsfw"
            :value="true"
          >NSFW</label>
        </div>
      </div>
      <div class="field">
        <label for="me-vis">這張圖的隱私</label>
        <select
          id="me-vis"
          v-model="form.visibility_override"
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

      <div
        class="row"
        style="gap:8px"
      >
        <button
          v-if="media.status === 'active'"
          class="btn sm"
          type="button"
          :disabled="isAvatar"
          @click="emit('avatar', media.id)"
        >
          {{ isAvatar ? '目前是頭像' : '設為獸設頭像' }}
        </button>
        <span class="sp" />
        <button
          v-if="!confirmDelete"
          class="btn sm danger"
          type="button"
          @click="confirmDelete = true"
        >
          刪除圖片
        </button>
        <template v-else>
          <span
            class="muted"
            style="font-size:12px"
          >原檔與衍生版會一起刪除，無法復原。</span>
          <button
            class="btn sm danger"
            type="button"
            :disabled="busy"
            @click="remove"
          >
            確認刪除
          </button>
        </template>
      </div>
    </template>
    <template #footer>
      <button
        class="btn"
        type="button"
        @click="media = null"
      >
        取消
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="save"
      >
        儲存
      </button>
    </template>
  </PawDialog>
</template>
