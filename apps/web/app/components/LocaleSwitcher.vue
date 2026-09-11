<script setup lang="ts">
const { t } = useI18n()
const { locale, available, switchLocale } = useLocale()

function onChange(e: Event) {
  switchLocale((e.target as HTMLSelectElement).value)
}
</script>

<template>
  <label class="lang">
    <UIcon name="i-lucide-languages" />
    <span class="sr-only">{{ t('common.locale.label') }}</span>
    <select
      :value="locale"
      :aria-label="t('common.locale.label')"
      @change="onChange"
    >
      <option
        v-for="l in available"
        :key="l.code"
        :value="l.code"
      >
        {{ l.name }}
      </option>
    </select>
  </label>
</template>

<style scoped>
.lang {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 999px;
  color: var(--muted, #6b6b7b);
  font-size: 13px;
  cursor: pointer;
}
.lang:hover {
  background: var(--soft, rgba(0, 0, 0, 0.05));
}
.lang select {
  appearance: none;
  background: transparent;
  border: 0;
  color: inherit;
  font: inherit;
  cursor: pointer;
  padding-right: 2px;
}
.lang select:focus-visible {
  outline: 2px solid var(--brand, #ff7a59);
  outline-offset: 2px;
  border-radius: 4px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}
</style>
