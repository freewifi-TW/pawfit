<script setup lang="ts">
/**
 * 嵌入頁專用 layout（FR-7.4）：沒有站內導覽、沒有 toaster、沒有登入狀態。
 * body 透明，讓卡片本身決定底色，嵌在深色個人站也不會有白框。
 */
useHead({
  bodyAttrs: { class: 'embed-body' },
  meta: [{ name: 'robots', content: 'noindex, nofollow, noai, noimageai' }]
})
</script>

<template>
  <div class="embed-shell">
    <slot />
  </div>
</template>

<style>
.embed-body { background: transparent; min-height: 0; overflow: hidden; }
.embed-shell { width: 100%; height: 100vh; }
</style>
