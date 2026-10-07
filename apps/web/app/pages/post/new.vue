<script setup lang="ts">
/** 發文頁（FR-B3）。 */
definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()
const { feed: enabled } = useFeatures()
if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('feed.disabled') })
}
useSeoMeta({ title: () => t('feed.compose.title'), robots: 'noindex' })
</script>

<template>
  <section
    class="wrap narrow-feed"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          {{ t('feed.compose.title') }}
        </h1>
        <p>{{ t('feed.compose.subtitle') }}</p>
      </div>
    </div>
    <PostComposer />
  </section>
</template>
