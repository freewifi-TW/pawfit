<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <NuxtLink
    to="/"
    class="logo disp"
    :aria-label="t('common.logo.ariaLabel')"
  >
    <svg
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <circle
        cx="12"
        cy="15"
        r="5"
        fill="currentColor"
      />
      <circle
        cx="6"
        cy="9"
        r="2.4"
        fill="currentColor"
      />
      <circle
        cx="10"
        cy="5.5"
        r="2.4"
        fill="currentColor"
      />
      <circle
        cx="14.5"
        cy="5.5"
        r="2.4"
        fill="currentColor"
      />
      <circle
        cx="18.5"
        cy="9"
        r="2.4"
        fill="currentColor"
      />
    </svg>Pawfit
  </NuxtLink>
</template>
