<script setup lang="ts">
const { t } = useI18n()
// <html lang> 與 hreflang 由 i18n 依目前語言產生
const head = useLocaleHead({ dir: true, lang: true, seo: true })

useHead(() => ({
  htmlAttrs: head.value.htmlAttrs,
  link: head.value.link,
  meta: head.value.meta,
  // 沒有頁面標題時只顯示站名
  titleTemplate: (title?: string) => (title ? `${title} · ${t('common.brand')}` : t('common.brand'))
}))

useSeoMeta({
  ogSiteName: () => t('common.brand'),
  ogType: 'website',
  twitterCard: 'summary_large_image'
})
</script>

<template>
  <!-- 一般頁面用 layouts/default.vue（含導覽與 toaster）；嵌入卡片用 layouts/embed.vue -->
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>
