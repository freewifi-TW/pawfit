<script setup lang="ts">
import type { Media, MediaKind, Visibility } from '~/types/api'

/** 單張圖的編輯：說明、credit、分類、分級、隱私覆寫、設為頭像、刪除。 */
const media = defineModel<Media | null>({ default: null })
defineProps<{ fursonaVisibility: Visibility, isAvatar: boolean }>()
const emit = defineEmits<{ updated: [media: Media], deleted: [id: string], avatar: [id: string | null] }>()

const api = useApi()
const notify = useNotify()
const { t } = useI18n()
const { kindLabelLong, visibilityLabel } = useLabels()

const KINDS: MediaKind[] = ['art2d', 'model3d', 'photo']
const VISIBILITIES: Visibility[] = ['public', 'unlisted', 'private']

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
    notify.ok(t('media.edit.saved'))
    media.value = null
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    notify.err(t('media.edit.saveFailed'), err.message)
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
    notify.ok(t('media.edit.deleted'))
    media.value = null
  } catch (e) {
    notify.err(t('media.edit.deleteFailed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PawDialog
    v-model:open="open"
    :title="t('media.edit.title')"
    :description="media?.status === 'processing' ? t('media.edit.processingNote') : ''"
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
          <label for="me-caption">{{ t('media.edit.caption') }}</label>
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
          <label for="me-credit">{{ t('media.edit.credit') }}</label>
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
          <label for="me-credit-url">{{ t('media.edit.creditUrl') }}</label>
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
        <span class="lbl">{{ t('media.edit.kind') }}</span>
        <div class="seg">
          <label
            v-for="k in KINDS"
            :key="k"
          >
            <input
              v-model="form.kind"
              type="radio"
              name="me-kind"
              :value="k"
            >{{ kindLabelLong(k) }}
          </label>
        </div>
      </div>
      <div class="field">
        <span class="lbl">{{ t('media.edit.rating') }}</span>
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
        <label for="me-vis">{{ t('media.edit.visibility') }}</label>
        <select
          id="me-vis"
          v-model="form.visibility_override"
          class="input"
        >
          <option value="">
            {{ t('media.edit.inherit', { visibility: visibilityLabel(fursonaVisibility) }) }}
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
          {{ isAvatar ? t('media.edit.isAvatar') : t('media.edit.setAvatar') }}
        </button>
        <span class="sp" />
        <button
          v-if="!confirmDelete"
          class="btn sm danger"
          type="button"
          @click="confirmDelete = true"
        >
          {{ t('media.edit.delete') }}
        </button>
        <template v-else>
          <span
            class="muted"
            style="font-size:12px"
          >{{ t('media.edit.deleteWarning') }}</span>
          <button
            class="btn sm danger"
            type="button"
            :disabled="busy"
            @click="remove"
          >
            {{ t('media.edit.confirmDelete') }}
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
        {{ t('media.common.cancel') }}
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="save"
      >
        {{ t('media.common.save') }}
      </button>
    </template>
  </PawDialog>
</template>
