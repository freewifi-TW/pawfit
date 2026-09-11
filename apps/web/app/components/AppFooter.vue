<script setup lang="ts">
const { t } = useI18n()
const year = new Date().getFullYear()
</script>

<template>
  <footer class="foot">
    <span>© {{ year }} {{ t('common.brand') }}</span>
    <NuxtLink to="/terms">
      {{ t('common.legal.terms') }}
    </NuxtLink>
    <NuxtLink to="/guidelines">
      {{ t('common.legal.guidelines') }}
    </NuxtLink>
    <NuxtLink to="/privacy">
      {{ t('common.legal.privacy') }}
    </NuxtLink>
    <a href="mailto:hello@pawfit.app">{{ t('common.footer.contact') }}</a>
    <span class="sp" />
    <span>{{ t('common.footer.phase') }}</span>
  </footer>
</template>
