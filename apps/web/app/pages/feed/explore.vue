<script setup lang="ts">
/** 探索河道（FR-B5.2）：全站公開貼文，標籤 AND 篩選（query ?tags=a,b）。訪客可看，NSFW 由後端依矩陣過濾。 */
const { t } = useI18n()
const { feed: enabled } = useFeatures()
if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.disabled') })
}
useSeoMeta({ title: () => t('feed.explore.title'), description: () => t('feed.explore.subtitle') })

const route = useRoute()
const router = useRouter()
const tags = computed(() => String(route.query.tags ?? '').split(',').map(s => s.trim()).filter(Boolean))
const input = ref(tags.value.join(', '))
watch(tags, v => (input.value = v.join(', ')))

const feed = await useFeed(computed(() => `feed-explore-${tags.value.join(',')}`).value, () =>
  tags.value.length ? `/feed/explore?tags=${encodeURIComponent(tags.value.join(','))}` : '/feed/explore')
watch(tags, () => feed.refresh())

function apply() {
  const list = input.value.split(/[,，]/).map(s => s.trim()).filter(Boolean).slice(0, 5)
  router.push({ path: '/feed/explore', query: list.length ? { tags: list.join(',') } : {} })
}
function clear() {
  input.value = ''
  router.push({ path: '/feed/explore' })
}
</script>

<template>
  <section
    class="wrap narrow-feed"
    style="padding-bottom:48px"
  >
    <FeedHeader active="explore" />
    <form
      class="row"
      style="margin-bottom:16px"
      @submit.prevent="apply"
    >
      <input
        v-model="input"
        class="input"
        style="flex:1;min-width:220px"
        :placeholder="t('feed.explore.tagsPlaceholder')"
      >
      <button
        class="btn"
        type="submit"
      >
        {{ t('feed.explore.filter') }}
      </button>
      <button
        v-if="tags.length"
        class="btn ghost"
        type="button"
        @click="clear"
      >
        {{ t('feed.explore.clear') }}
      </button>
    </form>
    <div
      v-if="tags.length"
      class="row"
      style="margin-bottom:12px;font-size:13px"
    >
      <span class="muted">{{ t('feed.explore.filtering') }}</span>
      <span
        v-for="tag in tags"
        :key="tag"
        class="pill accent"
      >{{ tag }}</span>
    </div>

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
        {{ t('feed.explore.empty') }}
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
