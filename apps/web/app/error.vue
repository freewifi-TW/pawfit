<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

const title = computed(() => {
  switch (props.error.statusCode) {
    case 404: return '找不到這一頁'
    case 403: return '沒有權限'
    default: return '出了點問題'
  }
})
const hint = computed(() => {
  if (props.error.statusCode === 404) return '連結可能已停用、獸設被設為私人，或這頁本來就不存在。'
  return props.error.statusMessage || props.error.message || '請稍後再試一次。'
})

useHead({ title: title.value })
</script>

<template>
  <UApp>
    <AppHeader />
    <main class="wrap narrow auth">
      <div
        class="card"
        style="text-align:center"
      >
        <div style="font-size:56px;line-height:1">
          🐾
        </div>
        <h1 class="disp">
          {{ title }}
        </h1>
        <p class="sub">
          {{ hint }}
        </p>
        <div
          class="row"
          style="justify-content:center;margin-top:18px"
        >
          <button
            class="btn primary"
            @click="clearError({ redirect: '/' })"
          >
            回首頁
          </button>
        </div>
      </div>
    </main>
    <AppFooter />
  </UApp>
</template>
