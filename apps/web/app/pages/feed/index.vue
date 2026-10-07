<script setup lang="ts">
/** 好友河道（FR-B5.1）：好友與自己的公開／限好友貼文，時間序、cursor 分頁。 */
definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()
const { feed: enabled } = useFeatures()
if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.disabled') })
}
useSeoMeta({ title: () => t('feed.friends.title'), robots: 'noindex' })

const feed = await useFeed('feed-friends', () => '/feed/friends')
</script>

<template>
  <section
    class="wrap narrow-feed"
    style="padding-bottom:48px"
  >
    <FeedHeader active="friends" />
    <div
      class="stack"
      style="gap:16px"
    >
      <PostCard
        v-for="p in feed.posts.value"
        :key="p.id"
        :post="p"
        @update="feed.replace"
        @deleted="feed.remove"
      />
      <div
        v-if="!feed.posts.value.length && !feed.pending.value"
        class="empty"
      >
        {{ t('feed.friends.empty') }}
      </div>
      <div
        v-if="feed.hasMore.value"
        class="row"
        style="justify-content:center"
      >
        <button
          class="btn"
          type="button"
          :disabled="feed.loading.value"
          @click="feed.loadMore"
        >
          {{ feed.loading.value ? t('feed.loading') : t('feed.loadMore') }}
        </button>
      </div>
    </div>
  </section>
</template>
