<script setup lang="ts">
import type { Post, PostComment, PostCommentPage } from '~/types/api'

/** 單篇貼文（FR-B3、FR-B6）：完整內容＋單層留言。看不到的貼文由後端 404。 */
const route = useRoute()
const api = useApi()
const notify = useNotify()
const config = useRuntimeConfig()
const { t } = useI18n()
const { me } = useAuth()
const { formatDate } = useLabels()
const { feed: enabled } = useFeatures()
if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.disabled') })
}
const id = route.params.id as string

const { data: post, error } = await useAsyncData(`post-${id}`, () => api<Post>(`/posts/${id}`))
if (error.value || !post.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.post.notFound') })
}

const { data: commentPage } = await useAsyncData(`post-${id}-comments`, () => api<PostCommentPage>(`/posts/${id}/comments`), {
  default: () => ({ data: [], next_after: null, total: 0 })
})
const comments = ref<PostComment[]>([])
const nextAfter = ref<string | null>(null)
watch(commentPage, (c) => {
  comments.value = c?.data ?? []
  nextAfter.value = c?.next_after ?? null
}, { immediate: true })

const cover = computed(() => post.value!.media.find(m => !m.is_nsfw && post.value!.state === 'show'))
useSeoMeta({
  title: () => t('feed.post.title', { name: post.value!.author.display_name }),
  description: () => post.value!.body.slice(0, 160),
  ogImage: () => cover.value ? `${config.public.siteUrl}${cover.value.urls.display}` : undefined,
  ogUrl: () => post.value!.url,
  robots: () => (post.value!.visibility === 'public' && !post.value!.is_nsfw ? 'index, follow' : 'noindex, nofollow')
})

const body = ref('')
const busy = ref(false)
const reportingComment = ref<PostComment | null>(null)

async function send() {
  if (!body.value.trim()) return
  busy.value = true
  try {
    const c = await api<PostComment>(`/posts/${id}/comments`, { method: 'POST', body: { body: body.value.trim() } })
    comments.value = [...comments.value, c]
    body.value = ''
    post.value = { ...post.value!, comment_count: post.value!.comment_count + 1 }
  } catch (e) {
    notify.err(t('feed.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function removeComment(c: PostComment) {
  busy.value = true
  try {
    await api(`/comments/${c.id}`, { method: 'DELETE' })
    comments.value = comments.value.filter(x => x.id !== c.id)
    post.value = { ...post.value!, comment_count: Math.max(0, post.value!.comment_count - 1) }
    notify.ok(t('feed.notify.commentDeleted'))
  } catch (e) {
    notify.err(t('feed.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function moreComments() {
  if (!nextAfter.value) return
  const page = await api<PostCommentPage>(`/posts/${id}/comments?after=${nextAfter.value}`)
  comments.value = [...comments.value, ...page.data]
  nextAfter.value = page.next_after
}

async function onDeleted() {
  await navigateTo('/feed')
}
</script>

<template>
  <section
    v-if="post"
    class="wrap narrow-feed"
    style="padding:28px 20px 48px"
  >
    <PostCard
      :post="post"
      detail
      @update="p => (post = p)"
      @deleted="onDeleted"
    />

    <div
      id="comments"
      class="card"
      style="margin-top:18px"
    >
      <div class="hd">
        <span class="disp">{{ t('feed.post.comments') }}</span>
        <em>{{ post.comment_count }}</em>
      </div>
      <div
        class="bd stack"
        style="gap:12px"
      >
        <div
          v-for="c in comments"
          :key="c.id"
          class="comment"
        >
          <NuxtLink :to="`/u/${c.author.pawfit_id}`">
            <BlobAvatar
              :src="c.author.avatar_url"
              :size="32"
              :border="2"
              variant="user"
            />
          </NuxtLink>
          <div class="cbody">
            <div class="cmeta">
              <NuxtLink
                class="link"
                :to="`/u/${c.author.pawfit_id}`"
              >{{ c.author.display_name }}</NuxtLink>
              <span class="muted">@{{ c.author.pawfit_id }} · {{ formatDate(c.created_at, true) }}</span>
              <span class="sp" />
              <button
                v-if="c.can_delete"
                class="btn sm ghost"
                type="button"
                :disabled="busy"
                @click="removeComment(c)"
              >
                {{ t('feed.post.deleteComment') }}
              </button>
              <button
                v-else-if="me"
                class="btn sm ghost"
                type="button"
                @click="reportingComment = c"
              >
                {{ t('feed.post.reportComment') }}
              </button>
            </div>
            <p>{{ c.body }}</p>
          </div>
        </div>
        <div
          v-if="!comments.length"
          class="muted"
          style="font-size:13px"
        >
          {{ t('feed.post.noComments') }}
        </div>
        <button
          v-if="nextAfter"
          class="btn sm ghost"
          type="button"
          @click="moreComments"
        >
          {{ t('feed.post.loadMoreComments') }}
        </button>

        <form
          v-if="me?.is_onboarded"
          class="row"
          style="align-items:flex-start"
          @submit.prevent="send"
        >
          <textarea
            v-model="body"
            class="input"
            rows="2"
            maxlength="500"
            style="flex:1"
            :placeholder="t('feed.post.commentPlaceholder')"
            @keydown.ctrl.enter.prevent="send"
          />
          <button
            class="btn primary"
            type="submit"
            :disabled="busy || !body.trim()"
          >
            {{ t('feed.post.send') }}
          </button>
        </form>
        <NuxtLink
          v-else-if="!me"
          class="btn sm"
          to="/login"
          style="justify-self:start"
        >
          {{ t('feed.post.loginToComment') }}
        </NuxtLink>
      </div>
    </div>

    <ReportDialog
      v-if="reportingComment"
      :open="!!reportingComment"
      target-type="comment"
      :target-id="reportingComment.id"
      :target-label="t('feed.post.commentTarget', { id: reportingComment.author.pawfit_id })"
      @update:open="v => { if (!v) reportingComment = null }"
    />
  </section>
</template>

<style scoped>
.comment { display: flex; gap: 10px; align-items: flex-start; }
.cbody { flex: 1; min-width: 0; }
.cmeta { display: flex; align-items: center; gap: 8px; font-size: 12px; flex-wrap: wrap; }
.cbody p { margin: 4px 0 0; white-space: pre-wrap; font-size: 14px; }
</style>
