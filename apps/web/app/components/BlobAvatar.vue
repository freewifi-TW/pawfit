<script setup lang="ts">
/** Pawfit 圓潤不規則頭像；沒有圖時退回漸層。 */
const props = withDefaults(defineProps<{
  src?: string | null
  size?: number
  variant?: 'fursona' | 'user'
  gradient?: string
  alt?: string
  border?: number
}>(), {
  src: null,
  size: 72,
  variant: 'fursona',
  gradient: '',
  alt: '',
  border: 4
})

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  borderWidth: `${props.border}px`,
  background: props.src ? undefined : (props.gradient || undefined)
}))
</script>

<template>
  <i
    class="avatar"
    :class="{ user: variant === 'user' }"
    :style="style"
    role="img"
    :aria-label="alt"
  >
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      loading="lazy"
    >
  </i>
</template>
