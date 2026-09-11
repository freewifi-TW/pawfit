<script setup lang="ts">
import type { PaletteEntry } from '~/types/api'

/** 分享頁色票：點一下複製色碼。 */
defineProps<{ palette: PaletteEntry[] }>()
const notify = useNotify()
const { t } = useI18n()

async function copy(hex: string) {
  try {
    await navigator.clipboard.writeText(hex)
    notify.ok(t('media.palette.copied', { hex }))
  } catch {
    notify.err(t('media.palette.clipboardError'))
  }
}
</script>

<template>
  <div class="palette">
    <button
      v-for="(p, i) in palette"
      :key="i"
      class="chip"
      type="button"
      :title="t('media.palette.copyTitle', { hex: p.hex })"
      @click="copy(p.hex)"
    >
      <i :style="`background:${p.hex}`" />
      <span class="n">{{ p.name || t('media.common.untitled') }}</span>
      <span class="h mono">{{ p.hex }}</span>
      <span
        v-if="p.note"
        class="note"
      >{{ p.note }}</span>
    </button>
  </div>
</template>
