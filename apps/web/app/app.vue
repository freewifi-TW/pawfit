<script setup lang="ts">
useHead({
  htmlAttrs: { lang: 'zh-Hant-TW' },
  // 沒有頁面標題時只顯示站名
  titleTemplate: (title?: string) => (title ? `${title} · Pawfit 爪搭` : 'Pawfit 爪搭')
})

useSeoMeta({
  ogSiteName: 'Pawfit 爪搭',
  ogType: 'website',
  twitterCard: 'summary_large_image'
})
</script>

<template>
  <UApp :toaster="{ position: 'bottom-center' }">
    <AppHeader />
    <main>
      <NuxtPage />
    </main>
    <AppFooter />
  </UApp>
</template>
