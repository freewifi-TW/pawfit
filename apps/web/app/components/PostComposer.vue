<script setup lang="ts">
import type { Fursona, Media, Post } from '~/types/api'

/**
 * 發文／編輯表單（FR-B3）。
 * 新增：選獸設 → 從該獸設圖庫勾圖（依點選順序）→ 文字、標籤（自動帶入獸設標籤）、隱私、NSFW。
 * 編輯：圖片組成鎖定，只能改文字、標籤、隱私；NSFW 若被來源鎖定不可改回。
 */
const props = defineProps<{ post?: Post | null }>()

const { t } = useI18n()
const api = useApi()
const notify = useNotify()

const MAX = 10
const editing = computed(() => !!props.post)
// 換獸頭工具（/post/new/head-sticker）完成後帶 ?fursona=&media= 進來
const route = useRoute()
const presetMedia = String(route.query.media ?? '').split(',').filter(Boolean)
const presetFursona = typeof route.query.fursona === 'string' ? route.query.fursona : ''

const { data: fursonas } = await useAsyncData('compose-fursonas', () => api<Fursona[]>('/fursonas'), { default: () => [] })
const fursonaId = ref<string>(props.post?.fursona?.id ?? (presetFursona && fursonas.value.some(f => f.id === presetFursona) ? presetFursona : '') ?? fursonas.value.find(f => f.is_representative)?.id ?? fursonas.value[0]?.id ?? '')
if (!fursonaId.value) fursonaId.value = fursonas.value.find(f => f.is_representative)?.id ?? fursonas.value[0]?.id ?? ''
const { data: fursonaDetail, pending: loadingMedia } = await useAsyncData(
  'compose-fursona-detail',
  () => fursonaId.value && !editing.value ? api<Fursona>(`/fursonas/${fursonaId.value}`) : Promise.resolve(null),
  { watch: [fursonaId] }
)
const pickable = computed<Media[]>(() => (fursonaDetail.value?.media ?? []).filter(m => m.status === 'active' || (presetMedia.includes(m.id) && m.status === 'processing')))

const selected = ref<string[]>(props.post?.media.map(m => m.id) ?? presetMedia)
const body = ref(props.post?.body ?? '')
const tags = ref<string[]>(props.post?.tags ?? [])
const visibility = ref<'public' | 'friends'>(props.post?.visibility ?? 'public')
const nsfw = ref(props.post?.is_nsfw ?? false)
const busy = ref(false)

// 選定獸設後自動帶入母標籤（FR-B3.2）；只在新增時、且使用者尚未自行改過標籤
const tagsTouched = ref(false)
watch(fursonaDetail, (f) => {
  if (!editing.value && f && !tagsTouched.value) tags.value = [...f.tags]
  // 預選的圖（換獸頭輸出）可能還在 processing，暫時保留；其餘只留這隻獸設現有的圖
  if (!editing.value) selected.value = selected.value.filter(id => presetMedia.includes(id) || pickable.value.some(m => m.id === id))
}, { immediate: true })
watch(tags, () => (tagsTouched.value = true))

const nsfwLocked = computed(() => {
  if (editing.value) return !!props.post?.is_nsfw && (props.post.media.some(m => m.is_nsfw) || false)
  return !!fursonaDetail.value?.is_nsfw || pickable.value.some(m => selected.value.includes(m.id) && m.is_nsfw)
})
watch(nsfwLocked, (v) => {
  if (v) nsfw.value = true
}, { immediate: true })

function toggle(id: string) {
  const i = selected.value.indexOf(id)
  if (i >= 0) selected.value.splice(i, 1)
  else if (selected.value.length < MAX) selected.value.push(id)
  else notify.err(t('feed.compose.maxReached', { max: MAX }))
}

async function submit() {
  if (!editing.value && !selected.value.length) return notify.err(t('feed.compose.pickOne'))
  busy.value = true
  try {
    if (editing.value) {
      const updated = await api<Post>(`/posts/${props.post!.id}`, {
        method: 'PATCH',
        body: { body: body.value, tags: tags.value, visibility: visibility.value, ...(nsfwLocked.value ? {} : { is_nsfw: nsfw.value }) }
      })
      notify.ok(t('feed.notify.saved'))
      await navigateTo(`/post/${updated.id}`)
    } else {
      const created = await api<Post>('/posts', {
        method: 'POST',
        body: { fursona_id: fursonaId.value || null, media_ids: selected.value, body: body.value, tags: tags.value, visibility: visibility.value, is_nsfw: nsfw.value }
      })
      notify.ok(t('feed.notify.posted'))
      await navigateTo(`/post/${created.id}`)
    }
  } catch (e) {
    const err = apiError(e)
    notify.err(t('feed.notify.failed'), Object.values(err.errors)[0] || err.message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <form
    class="stack"
    style="gap:18px"
    @submit.prevent="submit"
  >
    <div
      v-if="!editing"
      class="card"
    >
      <h2 class="disp">
        {{ t('feed.compose.fursona') }}
      </h2>
      <div
        class="bd stack"
        style="gap:10px"
      >
        <select
          v-model="fursonaId"
          class="input"
        >
          <option value="">
            {{ t('feed.compose.fursonaNone') }}
          </option>
          <option
            v-for="f in fursonas"
            :key="f.id"
            :value="f.id"
          >
            {{ f.name }}<template v-if="f.species">
              · {{ f.species }}
            </template>
          </option>
        </select>
        <p
          class="muted"
          style="font-size:12px;margin:0"
        >
          {{ t('feed.compose.fursonaHint') }}
        </p>
      </div>
    </div>

    <div class="card">
      <div class="hd">
        <span class="disp">{{ t('feed.compose.pickTitle') }}</span>
        <em v-if="!editing">{{ t('feed.compose.pickCount', { n: selected.length, max: MAX }) }}</em>
      </div>
      <div class="bd">
        <template v-if="editing">
          <div class="pick">
            <div
              v-for="m in post!.media"
              :key="m.id"
              class="pick-item on"
            >
              <img
                :src="m.urls.thumb"
                :alt="m.caption || ''"
              >
            </div>
          </div>
          <p
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            {{ t('feed.compose.mediaLocked') }}
          </p>
        </template>
        <template v-else>
          <div
            v-if="pickable.length"
            class="pick"
          >
            <button
              v-for="m in pickable"
              :key="m.id"
              type="button"
              class="pick-item"
              :class="{ on: selected.includes(m.id) }"
              :title="m.caption || ''"
              @click="toggle(m.id)"
            >
              <img
                :src="m.urls.thumb"
                :alt="m.caption || ''"
                loading="lazy"
              >
              <span
                v-if="selected.includes(m.id)"
                class="order"
              >{{ selected.indexOf(m.id) + 1 }}</span>
              <span
                v-if="m.is_nsfw"
                class="badge-nsfw"
              >NSFW</span>
            </button>
          </div>
          <div
            v-else
            class="empty"
          >
            {{ loadingMedia ? t('feed.loading') : (fursonaId ? t('feed.compose.pickEmpty') : t('feed.compose.pickAnyEmpty')) }}
          </div>
        </template>
      </div>
    </div>

    <div class="card">
      <h2 class="disp">
        {{ t('feed.compose.body') }}
      </h2>
      <div
        class="bd stack"
        style="gap:14px"
      >
        <textarea
          v-model="body"
          class="input"
          rows="5"
          maxlength="2000"
          :placeholder="t('feed.compose.bodyPlaceholder')"
        />
        <div>
          <div
            class="lbl"
            style="font-size:13px;font-weight:700;margin-bottom:6px"
          >
            {{ t('feed.compose.tags') }}
          </div>
          <TagEditor v-model="tags" />
        </div>
      </div>
    </div>

    <div class="card">
      <h2 class="disp">
        {{ t('feed.compose.visibility') }}
      </h2>
      <div
        class="bd stack"
        style="gap:12px"
      >
        <div class="radio-list">
          <label class="radio">
            <input
              v-model="visibility"
              type="radio"
              value="public"
            >
            <span><b>{{ t('feed.compose.public') }}</b><small>{{ t('feed.compose.publicHint') }}</small></span>
          </label>
          <label class="radio">
            <input
              v-model="visibility"
              type="radio"
              value="friends"
            >
            <span><b>{{ t('feed.friendsOnly') }}</b><small>{{ t('feed.compose.friendsOnlyHint') }}</small></span>
          </label>
        </div>
        <label class="check">
          <input
            v-model="nsfw"
            type="checkbox"
            :disabled="nsfwLocked"
          >
          <span>{{ t('feed.compose.nsfw') }}<br><small
            v-if="nsfwLocked"
            class="muted"
          >{{ t('feed.compose.nsfwLocked') }}</small></span>
        </label>
      </div>
    </div>

    <div class="row">
      <span class="sp" />
      <button
        class="btn primary lg"
        type="submit"
        :disabled="busy || (!editing && !selected.length)"
      >
        {{ editing ? t('feed.compose.save') : t('feed.compose.submit') }}
      </button>
    </div>
  </form>
</template>

<style scoped>
.pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
.pick-item { position: relative; aspect-ratio: 1; border-radius: var(--r-in); overflow: hidden; border: var(--border) solid var(--line); background: var(--paper-2); padding: 0; }
.pick-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pick-item.on { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.pick-item .order { position: absolute; top: 6px; left: 6px; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: var(--accent-ink); font-size: 12px; font-weight: 800; display: grid; place-items: center; }
.pick-item .badge-nsfw { position: absolute; right: 6px; bottom: 6px; top: auto; }
textarea.input { resize: vertical; }
</style>
