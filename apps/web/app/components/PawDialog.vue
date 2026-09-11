<script setup lang="ts">
/** 專案風格的對話框：包一層 UModal，標題用 display 字體。 */
const open = defineModel<boolean>('open', { default: false })

withDefaults(defineProps<{
  title: string
  description?: string
  wide?: boolean
}>(), {
  description: '',
  wide: false
})
</script>

<template>
  <UModal
    v-model:open="open"
    :title="title"
    :description="description"
    :ui="{
      content: wide ? 'sm:max-w-2xl' : 'sm:max-w-lg',
      title: 'disp text-xl',
      description: 'sub text-sm'
    }"
  >
    <template #body>
      <slot />
    </template>
    <template
      v-if="$slots.footer"
      #footer
    >
      <div
        class="dlg-ft"
        style="width:100%"
      >
        <slot name="footer" />
      </div>
    </template>
  </UModal>
</template>
