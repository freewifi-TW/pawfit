<script setup lang="ts">
import type { Media } from '~/types/api'

const media = defineModel<Media | null>({ default: null })

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') media.value = null
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="media"
      class="lightbox"
      role="dialog"
      aria-modal="true"
      :aria-label="media.caption || '圖片'"
      @click="media = null"
    >
      <img
        :src="media.urls.display"
        :alt="media.caption || ''"
        @click.stop
      >
    </div>
  </Teleport>
</template>
