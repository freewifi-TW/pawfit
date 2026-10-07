<script setup lang="ts">
import type { Post } from '~/types/api'

/** 編輯貼文（FR-B3.5）：只能改文字、標籤、隱私；圖片組成鎖定。 */
definePageMeta({ middleware: 'onboarded' })

const route = useRoute()
const api = useApi()
const { t } = useI18n()
const { feed: enabled } = useFeatures()
if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.disabled') })
}
const id = route.params.id as string
const { data: post, error } = await useAsyncData(`post-edit-${id}`, () => api<Post>(`/posts/${id}`))
if (error.value || !post.value || !post.value.can_edit) {
  throw createError({ statusCode: 404, statusMessage: t('feed.post.notFound') })
}
useSeoMeta({ title: () => t('feed.compose.editTitle'), robots: 'noindex' })
</script>

<template>
  <section
    class="wrap narrow-feed"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          {{ t('feed.compose.editTitle') }}
        </h1>
      </div>
      <span class="sp" />
      <NuxtLink
        class="btn sm ghost"
        :to="`/post/${id}`"
      >
        ← {{ t('fursona.actions.cancel') }}
      </NuxtLink>
    </div>
    <PostComposer :post="post" />
  </section>
</template>
