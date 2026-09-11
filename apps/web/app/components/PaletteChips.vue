<script setup lang="ts">
import type { PaletteEntry } from '~/types/api'

/** 分享頁色票：點一下複製色碼。 */
defineProps<{ palette: PaletteEntry[] }>()
const notify = useNotify()

async function copy(hex: string) {
  try {
    await navigator.clipboard.writeText(hex)
    notify.ok(`已複製 ${hex}`)
  } catch {
    notify.err('無法存取剪貼簿')
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
      :title="`複製 ${p.hex}`"
      @click="copy(p.hex)"
    >
      <i :style="`background:${p.hex}`" />
      <span class="n">{{ p.name || '未命名' }}</span>
      <span class="h mono">{{ p.hex }}</span>
      <span
        v-if="p.note"
        class="note"
      >{{ p.note }}</span>
    </button>
  </div>
</template>
