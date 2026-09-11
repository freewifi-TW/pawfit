<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const { t } = useI18n()

const title = computed(() => {
  switch (props.error.statusCode) {
    case 404: return t('common.errorPage.notFound')
    case 403: return t('common.errorPage.forbidden')
    default: return t('common.errorPage.generic')
  }
})
const hint = computed(() => {
  if (props.error.statusCode === 404) return t('common.errorPage.notFoundHint')
  return props.error.statusMessage || props.error.message || t('common.errorPage.retryHint')
})

useHead({ title })
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
            {{ t('common.errorPage.backHome') }}
          </button>
        </div>
      </div>
    </main>
    <AppFooter />
  </UApp>
</template>
