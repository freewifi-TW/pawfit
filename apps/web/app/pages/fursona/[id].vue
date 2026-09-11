<script setup lang="ts">
import type { Fursona, Media, PaletteEntry, ShareLink, Visibility } from '~/types/api'
import { paletteGradient, VISIBILITY_PILL } from '~/utils/labels'

definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()
const { visibilityLabel, formatDate } = useLabels()
const route = useRoute()
const api = useApi()
const notify = useNotify()
const { me, fetchMe } = useAuth()
const id = route.params.id as string

const { data: fursona, error } = await useAsyncData(`fursona-${id}`, () => api<Fursona>(`/fursonas/${id}`))
if (error.value || !fursona.value) {
  throw createError({ statusCode: apiError(error.value).status === 403 ? 404 : (apiError(error.value).status || 404), statusMessage: t('fursona.notFound') })
}

useSeoMeta({ title: () => t('fursona.seoTitle', { name: fursona.value?.name ?? '' }) })

type Tab = 'basic' | 'gallery' | 'palette' | 'tags' | 'privacy'
const tab = ref<Tab>((route.hash.replace('#', '') as Tab) || 'basic')
watch(tab, v => history.replaceState(null, '', `#${v}`))

// ---- 可編輯欄位的工作副本 ----
const form = reactive({
  name: '',
  species: '',
  bio: '',
  tags: [] as string[],
  palette: [] as PaletteEntry[],
  visibility: 'public' as Visibility,
  is_nsfw: false,
  is_representative: false
})

function loadForm(f: Fursona) {
  form.name = f.name
  form.species = f.species ?? ''
  form.bio = f.bio ?? ''
  form.tags = [...f.tags]
  form.palette = f.palette.map(p => ({ hex: p.hex, name: p.name ?? '', note: p.note ?? '' }))
  form.visibility = f.visibility
  form.is_nsfw = f.is_nsfw
  form.is_representative = f.is_representative
}
loadForm(fursona.value)

const snapshot = () => JSON.stringify(form)
const saved = ref(snapshot())
const dirty = computed(() => snapshot() !== saved.value)
const busy = ref(false)
const errors = ref<Record<string, string>>({})

async function save() {
  if (!fursona.value) return
  errors.value = {}
  if (!form.name.trim()) {
    errors.value.name = t('fursona.basic.nameRequired')
    tab.value = 'basic'
    return
  }
  busy.value = true
  try {
    const updated = await api<Fursona>(`/fursonas/${id}`, {
      method: 'PATCH',
      body: {
        name: form.name.trim(),
        species: form.species.trim() || null,
        bio: form.bio.trim() || null,
        tags: form.tags,
        palette: form.palette,
        visibility: form.visibility,
        is_nsfw: form.is_nsfw,
        is_representative: form.is_representative
      }
    })
    fursona.value = updated
    loadForm(updated)
    saved.value = snapshot()
    notify.ok(t('fursona.notify.saved'))
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    notify.err(t('fursona.notify.saveFailed'), Object.values(err.errors)[0] || err.message)
  } finally {
    busy.value = false
  }
}

// 離開前提醒
onBeforeRouteLeave(() => {
  if (dirty.value && !window.confirm(t('fursona.notify.leaveConfirm'))) return false
})

// ---- 圖庫 ----
const media = computed(() => fursona.value?.media ?? [])
const uploading = ref(false)
const editing = ref<Media | null>(null)

function onUploaded(m: Media) {
  if (!fursona.value) return
  fursona.value = { ...fursona.value, media: [...media.value, m], media_count: (fursona.value.media_count ?? 0) + 1 }
  fetchMe(true)
}
function onUpdated(m: Media) {
  if (!fursona.value) return
  fursona.value = { ...fursona.value, media: media.value.map(x => (x.id === m.id ? m : x)) }
}
function onDeleted(mid: string) {
  if (!fursona.value) return
  fursona.value = {
    ...fursona.value,
    media: media.value.filter(x => x.id !== mid),
    media_count: Math.max(0, (fursona.value.media_count ?? 1) - 1),
    avatar_media_id: fursona.value.avatar_media_id === mid ? null : fursona.value.avatar_media_id
  }
  fetchMe(true)
}
async function setAvatar(mid: string | null) {
  try {
    const updated = await api<Fursona>(`/fursonas/${id}`, { method: 'PATCH', body: { avatar_media_id: mid } })
    fursona.value = { ...updated, media: media.value }
    notify.ok(t('fursona.notify.avatarUpdated'))
    editing.value = null
  } catch (e) {
    notify.err(t('fursona.notify.updateFailed'), apiError(e).message)
  }
}
async function reorder(ids: string[]) {
  if (!fursona.value) return
  const byId = new Map(media.value.map(m => [m.id, m]))
  fursona.value = { ...fursona.value, media: ids.map(i => byId.get(i)!).filter(Boolean) }
  try {
    await api(`/fursonas/${id}/media/reorder`, { method: 'POST', body: { ids } })
  } catch (e) {
    notify.err(t('fursona.notify.reorderFailed'), apiError(e).message)
  }
}

// 有處理中的圖就每 3 秒輪詢
const hasProcessing = computed(() => media.value.some(m => m.status === 'processing'))
let poll: ReturnType<typeof setInterval> | null = null
watch(hasProcessing, (v) => {
  if (v && !poll) {
    poll = setInterval(async () => {
      try {
        const fresh = await api<Fursona>(`/fursonas/${id}`)
        if (fursona.value) fursona.value = { ...fursona.value, media: fresh.media, cover_url: fresh.cover_url, avatar_url: fresh.avatar_url }
      } catch { /* 忽略單次失敗 */ }
    }, 3000)
  } else if (!v && poll) {
    clearInterval(poll)
    poll = null
  }
}, { immediate: true })
onBeforeUnmount(() => poll && clearInterval(poll))

function onShareChange(link: ShareLink | null) {
  if (fursona.value) fursona.value = { ...fursona.value, share_link: link }
}

// 刪除獸設
const confirmDelete = ref(false)
async function destroy() {
  busy.value = true
  try {
    await api(`/fursonas/${id}`, { method: 'DELETE' })
    saved.value = snapshot()
    notify.ok(t('fursona.notify.deleted'))
    await fetchMe(true)
    await navigateTo('/dashboard')
  } catch (e) {
    notify.err(t('fursona.notify.deleteFailed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

const visibilityOptions = computed<Array<{ value: Visibility, title: string, hint: string }>>(() =>
  (['public', 'unlisted', 'private'] as Visibility[]).map(value => ({
    value,
    title: visibilityLabel(value),
    hint: t(`fursona.privacy.hints.${value}`)
  }))
)
</script>

<template>
  <section
    v-if="fursona"
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="edhd">
      <BlobAvatar
        :src="fursona.avatar_url"
        :gradient="paletteGradient(fursona.palette)"
        :size="84"
        :alt="fursona.name"
      />
      <div>
        <h1 class="disp">
          {{ form.name || t('fursona.unnamed') }}
        </h1>
        <div class="meta">
          <span
            v-if="form.species"
            class="pill"
          >{{ form.species }}</span>
          <span
            class="pill"
            :class="VISIBILITY_PILL[form.visibility]"
          >{{ visibilityLabel(form.visibility) }}</span>
          <span
            v-if="form.is_representative"
            class="pill accent"
          >{{ t('fursona.header.representative') }}</span>
          <span
            v-if="form.is_nsfw"
            class="pill accent"
          >NSFW</span>
          <span
            v-if="fursona.removed_at"
            class="pill danger"
          >{{ t('fursona.header.removed') }}</span>
          <span class="pill">{{ t('fursona.header.updatedAt') }} <span class="mono">{{ formatDate(fursona.updated_at) }}</span></span>
        </div>
      </div>
      <div class="acts row">
        <NuxtLink
          v-if="fursona.share_link && fursona.visibility !== 'private'"
          class="btn"
          :to="`/s/${fursona.share_link.slug}`"
          target="_blank"
        >
          {{ t('fursona.header.previewShare') }}
        </NuxtLink>
        <button
          class="btn primary"
          type="button"
          :disabled="busy || !dirty"
          @click="save"
        >
          {{ dirty ? t('fursona.actions.save') : t('fursona.actions.saved') }}
        </button>
      </div>
    </div>

    <p
      v-if="fursona.removed_at"
      class="visitor-note"
      style="margin:0 0 18px;border-color:var(--danger);background:var(--danger-soft)"
    >
      {{ t('fursona.header.removedNotice') }}
    </p>

    <div
      class="tabs"
      role="tablist"
    >
      <button
        type="button"
        :class="{ on: tab === 'basic' }"
        @click="tab = 'basic'"
      >
        {{ t('fursona.tabs.basic') }}
      </button>
      <button
        type="button"
        :class="{ on: tab === 'gallery' }"
        @click="tab = 'gallery'"
      >
        {{ t('fursona.tabs.gallery') }} <span class="muted">{{ media.length }}</span>
      </button>
      <button
        type="button"
        :class="{ on: tab === 'palette' }"
        @click="tab = 'palette'"
      >
        {{ t('fursona.tabs.palette') }} <span class="muted">{{ form.palette.length }}</span>
      </button>
      <button
        type="button"
        :class="{ on: tab === 'tags' }"
        @click="tab = 'tags'"
      >
        {{ t('fursona.tabs.tags') }} <span class="muted">{{ form.tags.length }}</span>
      </button>
      <button
        type="button"
        :class="{ on: tab === 'privacy' }"
        @click="tab = 'privacy'"
      >
        {{ t('fursona.tabs.privacy') }}
      </button>
    </div>

    <!-- 基本資料 -->
    <div
      v-show="tab === 'basic'"
      class="ed-grid"
    >
      <div class="card">
        <h2 class="disp">
          {{ t('fursona.basic.title') }}
        </h2>
        <div class="bd">
          <div class="field">
            <label for="f-name">{{ t('fursona.basic.name') }}</label>
            <input
              id="f-name"
              v-model="form.name"
              class="input"
              :class="{ 'is-invalid': errors.name }"
              maxlength="60"
            >
            <div
              v-if="errors.name"
              class="err"
            >
              {{ errors.name }}
            </div>
          </div>
          <div class="field">
            <label for="f-species">{{ t('fursona.basic.species') }}</label>
            <input
              id="f-species"
              v-model="form.species"
              class="input"
              maxlength="60"
            >
          </div>
          <div
            class="field"
            style="margin:0"
          >
            <label for="f-bio">{{ t('fursona.basic.bio') }}</label>
            <textarea
              id="f-bio"
              v-model="form.bio"
              class="input"
              maxlength="4000"
              style="min-height:160px"
            />
            <div class="hint">
              {{ t('fursona.basic.bioHint') }}
            </div>
          </div>
        </div>
      </div>
      <div class="stack">
        <div class="card">
          <h2 class="disp">
            {{ t('fursona.avatar.title') }}
          </h2>
          <div
            class="bd stack"
            style="gap:16px"
          >
            <div class="row">
              <BlobAvatar
                :src="fursona.avatar_url"
                :gradient="paletteGradient(fursona.palette)"
                :size="72"
                :alt="fursona.name"
              />
              <div>
                <button
                  class="btn sm"
                  type="button"
                  @click="tab = 'gallery'"
                >
                  {{ t('fursona.avatar.pickFromGallery') }}
                </button>
                <div
                  class="hint muted"
                  style="font-size:12px;margin-top:6px"
                >
                  {{ t('fursona.avatar.hint') }}
                </div>
              </div>
            </div>
            <label class="switch">
              <input
                v-model="form.is_representative"
                type="checkbox"
              > {{ t('fursona.avatar.representative') }}<span
                class="muted"
                style="font-weight:400;font-size:12px"
              >{{ t('fursona.avatar.representativeHint') }}</span>
            </label>
          </div>
        </div>
        <div class="card">
          <h2 class="disp">
            {{ t('fursona.rating.title') }}
          </h2>
          <div
            class="bd stack"
            style="gap:12px"
          >
            <label class="switch">
              <input
                v-model="form.is_nsfw"
                type="checkbox"
              > {{ t('fursona.rating.nsfw') }}
            </label>
            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('fursona.rating.hint') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- 圖庫 -->
    <div
      v-show="tab === 'gallery'"
      class="card"
    >
      <GalleryGrid
        :media="media"
        owner
        @reorder="reorder"
        @edit="editing = $event"
      >
        <template #tools>
          <button
            class="btn primary"
            type="button"
            @click="uploading = true"
          >
            {{ t('fursona.gallery.upload') }}
          </button>
        </template>
        <template #empty>
          {{ t('fursona.gallery.empty') }}
        </template>
      </GalleryGrid>
    </div>

    <!-- 色票 -->
    <div
      v-show="tab === 'palette'"
      class="card"
    >
      <div class="hd">
        <span class="disp">{{ t('fursona.palette.title') }}</span><em>{{ t('fursona.palette.subtitle') }}</em>
      </div>
      <PaletteEditor v-model="form.palette" />
    </div>

    <!-- 標籤 -->
    <div v-show="tab === 'tags'">
      <TagEditor v-model="form.tags" />
    </div>

    <!-- 隱私與分享 -->
    <div
      v-show="tab === 'privacy'"
      class="ed-grid"
    >
      <div class="stack">
        <div class="card">
          <h2 class="disp">
            {{ t('fursona.privacy.title') }}
          </h2>
          <div class="bd radio-list">
            <label
              v-for="o in visibilityOptions"
              :key="o.value"
              class="radio"
            >
              <input
                v-model="form.visibility"
                type="radio"
                name="vis"
                :value="o.value"
              >
              <span><b>{{ o.title }}</b><small>{{ o.hint }}</small></span>
            </label>
            <label class="radio disabled">
              <input
                type="radio"
                name="vis"
                disabled
              >
              <span><b>{{ t('fursona.privacy.friendsOnly') }}</b> <span
                class="pill"
                style="font-size:10px"
              >{{ t('fursona.privacy.phase2') }}</span><small>{{ t('fursona.privacy.friendsHint') }}</small></span>
            </label>
            <p
              class="muted"
              style="font-size:12px;margin:4px 0 0"
            >
              {{ t('fursona.privacy.note') }}
            </p>
          </div>
        </div>
        <div class="card">
          <h2 class="disp">
            {{ t('fursona.delete.title') }}
          </h2>
          <div class="bd">
            <p
              class="sub"
              style="margin:0 0 12px;font-size:13px"
            >
              {{ t('fursona.delete.warning') }}
            </p>
            <div class="row">
              <button
                v-if="!confirmDelete"
                class="btn sm danger"
                type="button"
                @click="confirmDelete = true"
              >
                {{ t('fursona.delete.button') }}
              </button>
              <template v-else>
                <button
                  class="btn sm danger"
                  type="button"
                  :disabled="busy"
                  @click="destroy"
                >
                  {{ t('fursona.delete.confirm', { name: fursona.name }) }}
                </button>
                <button
                  class="btn sm ghost"
                  type="button"
                  @click="confirmDelete = false"
                >
                  {{ t('fursona.actions.cancel') }}
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
      <ShareLinkPanel
        :fursona="fursona"
        @change="onShareChange"
      />
    </div>

    <UploadDialog
      v-model:open="uploading"
      :fursona-id="fursona.id"
      :fursona-visibility="fursona.visibility"
      :max-bytes="me?.quota.max_file_bytes ?? 20 * 1024 * 1024"
      @uploaded="onUploaded"
    />
    <MediaEditDialog
      v-model="editing"
      :fursona-visibility="fursona.visibility"
      :is-avatar="!!editing && fursona.avatar_media_id === editing.id"
      @updated="onUpdated"
      @deleted="onDeleted"
      @avatar="setAvatar"
    />
  </section>
</template>
