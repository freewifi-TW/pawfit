<script setup lang="ts">
import type { Media, Post } from '~/types/api'
import { tagStyle } from '~/utils/labels'

/**
 * 貼文卡（FR-B5.4）：河道項目與單篇頁共用。
 * NSFW 由後端給 state（show / blur）；blur 時整張卡的圖模糊，點擊解鎖後本次瀏覽維持顯示。
 */
const props = withDefaults(defineProps<{ post: Post, detail?: boolean }>(), { detail: false })
const emit = defineEmits<{ update: [post: Post], deleted: [id: string] }>()

const { t } = useI18n()
const api = useApi()
const notify = useNotify()
const { me } = useAuth()
const { formatDate } = useLabels()

const unlocked = ref(false)
const isBlur = computed(() => props.post.state === 'blur' && !unlocked.value)
const liked = ref(props.post.liked_by_me)
const likeCount = ref(props.post.like_count)
watch(() => props.post, (p) => {
  liked.value = p.liked_by_me
  likeCount.value = p.like_count
})
const busy = ref(false)
const reporting = ref(false)
const confirmDelete = ref(false)
const lightbox = ref<Media | null>(null)
/** 燈箱吃 Media 型別；貼文的圖只有子集，補上固定欄位即可 */
function openLightbox(i: number) {
  const m = props.post.media[i]!
  lightbox.value = { ...m, fursona_id: props.post.fursona?.id ?? '', visibility_override: null, sort_order: i, status: 'active', created_at: props.post.created_at } as Media
}

const shownMedia = computed(() => props.detail ? props.post.media : props.post.media.slice(0, 4))
const extra = computed(() => props.detail ? 0 : Math.max(0, props.post.media.length - 4))
const bodyLines = computed(() => props.post.body.split(/\n{2,}/).map(s => s.trim()).filter(Boolean))

async function toggleLike() {
  if (!me.value) return navigateTo('/login')
  if (busy.value) return
  busy.value = true
  const next = !liked.value
  liked.value = next
  likeCount.value += next ? 1 : -1
  try {
    const res = await api<{ liked: boolean, like_count: number }>(`/posts/${props.post.id}/like`, { method: next ? 'POST' : 'DELETE' })
    liked.value = res.liked
    likeCount.value = res.like_count
    emit('update', { ...props.post, liked_by_me: res.liked, like_count: res.like_count })
  } catch (e) {
    liked.value = !next
    likeCount.value += next ? -1 : 1
    notify.err(t('feed.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function destroy() {
  busy.value = true
  try {
    await api(`/posts/${props.post.id}`, { method: 'DELETE' })
    notify.ok(t('feed.notify.deleted'))
    emit('deleted', props.post.id)
  } catch (e) {
    notify.err(t('feed.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
    confirmDelete.value = false
  }
}
</script>

<template>
  <article
    class="card post"
    :class="{ 'is-blur': isBlur }"
  >
    <header class="post-hd">
      <NuxtLink
        class="who"
        :to="`/u/${post.author.pawfit_id}`"
      >
        <BlobAvatar
          :src="post.author.avatar_url"
          :size="40"
          :border="2"
          variant="user"
        />
        <span>
          <b>{{ post.author.display_name }}</b>
          <small>@{{ post.author.pawfit_id }} · {{ formatDate(post.created_at, true) }}</small>
        </span>
      </NuxtLink>
      <span class="sp" />
      <span
        v-if="post.fursona"
        class="pill"
        :title="post.fursona.species ?? ''"
      >{{ post.fursona.name }}</span>
      <span
        v-if="post.visibility === 'friends'"
        class="pill accent"
      >{{ t('feed.friendsOnly') }}</span>
      <span
        v-if="post.is_nsfw"
        class="pill danger"
      >NSFW</span>
      <span
        v-if="post.can_edit && post.status !== 'active'"
        class="pill warn"
        :title="post.status_note ?? ''"
      >{{ t(`feed.status.${post.status}`) }}</span>
    </header>

    <div
      v-if="post.media.length"
      class="post-media"
      :class="[`n${Math.min(shownMedia.length, 4)}`]"
      @click="isBlur ? (unlocked = true) : null"
    >
      <button
        v-for="(m, i) in shownMedia"
        :key="m.id"
        type="button"
        class="post-img"
        :aria-label="m.caption || t('feed.openImage')"
        @click.stop="isBlur ? (unlocked = true) : (detail ? openLightbox(i) : navigateTo(`/post/${post.id}`))"
      >
        <img
          :src="m.urls.display"
          :alt="m.caption || ''"
          loading="lazy"
        >
        <span
          v-if="i === shownMedia.length - 1 && extra"
          class="more"
        >+{{ extra }}</span>
      </button>
      <div
        v-if="isBlur"
        class="blur-note"
      >
        {{ t('feed.blurNote') }}
      </div>
    </div>

    <div class="post-bd">
      <template v-if="detail">
        <p
          v-for="(p, i) in bodyLines"
          :key="i"
        >
          {{ p }}
        </p>
      </template>
      <p
        v-else-if="post.body"
        class="clamp"
      >
        {{ post.body }}
      </p>
      <div
        v-if="post.tags.length"
        class="tags"
      >
        <NuxtLink
          v-for="(tag, i) in post.tags"
          :key="tag"
          class="tag"
          :class="tagStyle(i).class"
          :style="tagStyle(i).style"
          :to="`/feed/explore?tags=${encodeURIComponent(tag)}`"
        >{{ tag }}</NuxtLink>
      </div>
      <div
        v-if="detail && post.media.some(m => m.credit_name)"
        class="muted"
        style="font-size:12px"
      >
        {{ t('feed.credits') }} {{ [...new Set(post.media.map(m => m.credit_name).filter(Boolean))].join('、') }}
      </div>
    </div>

    <footer class="post-ft">
      <button
        class="btn sm"
        :class="{ primary: liked }"
        type="button"
        :disabled="busy"
        :aria-pressed="liked"
        @click="toggleLike"
      >
        {{ liked ? '♥' : '♡' }} {{ likeCount }}
      </button>
      <NuxtLink
        class="btn sm ghost"
        :to="`/post/${post.id}#comments`"
      >
        💬 {{ post.comment_count }}
      </NuxtLink>
      <span class="sp" />
      <template v-if="post.can_edit && detail">
        <NuxtLink
          class="btn sm ghost"
          :to="`/post/${post.id}/edit`"
        >
          {{ t('feed.edit') }}
        </NuxtLink>
        <button
          v-if="!confirmDelete"
          class="btn sm ghost"
          type="button"
          style="color:var(--danger)"
          @click="confirmDelete = true"
        >
          {{ t('feed.delete') }}
        </button>
        <button
          v-else
          class="btn sm danger"
          type="button"
          :disabled="busy"
          @click="destroy"
        >
          {{ t('feed.deleteConfirm') }}
        </button>
      </template>
      <button
        v-else-if="!post.can_edit"
        class="btn sm ghost"
        type="button"
        @click="reporting = true"
      >
        {{ t('feed.report') }}
      </button>
    </footer>

    <ReportDialog
      v-model:open="reporting"
      target-type="post"
      :target-id="post.id"
      :target-label="t('feed.reportTarget', { id: post.author.pawfit_id })"
    />
    <MediaLightbox
      v-if="detail"
      v-model="lightbox"
    />
  </article>
</template>

<style scoped>
.post { display: grid; }
.post-hd { display: flex; align-items: center; gap: 8px; padding: 14px 18px 10px; flex-wrap: wrap; }
.who { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; min-width: 0; }
.who span { display: grid; line-height: 1.25; }
.who b { font-size: 14px; }
.who small { font-size: 12px; color: var(--ink-3); }
.post-media { position: relative; display: grid; gap: 3px; background: var(--paper-2); border-top: var(--border) solid var(--line); border-bottom: var(--border) solid var(--line); }
.post-media.n1 { grid-template-columns: 1fr; }
.post-media.n2 { grid-template-columns: 1fr 1fr; }
.post-media.n3 { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; }
.post-media.n3 .post-img:first-child { grid-row: 1 / 3; }
.post-media.n4 { grid-template-columns: 1fr 1fr; }
.post-img { position: relative; padding: 0; border: 0; background: var(--paper-2); display: block; overflow: hidden; cursor: pointer; }
.post-img img { width: 100%; height: 100%; object-fit: cover; display: block; max-height: 640px; transition: filter .2s; }
.n1 .post-img img { object-fit: contain; background: var(--paper-2); }
.post-img .more { position: absolute; inset: 0; display: grid; place-items: center; background: rgba(42,35,82,.55); color: #fff; font: 800 28px var(--font-disp), sans-serif; }
.is-blur .post-img img { filter: blur(22px) saturate(.6); transform: scale(1.08); }
.blur-note { position: absolute; inset: 0; display: grid; place-items: center; font-weight: 700; color: var(--ink); background: rgba(251,250,255,.35); pointer-events: none; text-align: center; padding: 20px; }
.post-bd { padding: 12px 18px 6px; display: grid; gap: 8px; }
.post-bd p { margin: 0; white-space: pre-wrap; }
.post-bd .clamp { display: -webkit-box; -webkit-line-clamp: 4; line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
.tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tags .tag { text-decoration: none; }
.post-ft { display: flex; align-items: center; gap: 8px; padding: 8px 18px 14px; flex-wrap: wrap; }
</style>
