<script setup lang="ts">
import { tagStyle } from '~/utils/labels'

/** 獸設標籤（FR-2.3）：Enter 新增、點 × 移除、常用標籤一鍵加入。 */
const tags = defineModel<string[]>({ default: () => [] })
const props = withDefaults(defineProps<{ max?: number }>(), { max: 30 })
const { t, tm, rt } = useI18n()

const input = ref('')
const suggestions = computed<string[]>(() => (tm('fursona.tags.suggestions') as unknown[]).map(s => rt(s as string)))

function add(raw: string) {
  const tag = raw.trim().replace(/\s+/g, ' ').slice(0, 20)
  if (!tag || tags.value.includes(tag) || tags.value.length >= props.max) return
  tags.value = [...tags.value, tag]
}
function submit() {
  input.value.split(/[,，]/).forEach(add)
  input.value = ''
}
function remove(tag: string) {
  tags.value = tags.value.filter(x => x !== tag)
}
</script>

<template>
  <div class="ed-grid">
    <div class="card">
      <div class="hd">
        <span class="disp">{{ t('fursona.tags.title') }}</span><em>{{ t('fursona.tags.subtitle') }}</em>
      </div>
      <div
        v-if="tags.length"
        class="tagbox"
      >
        <span
          v-for="(tag, i) in tags"
          :key="tag"
          class="tag"
          :class="tagStyle(i).class"
          :style="tagStyle(i).style"
        >{{ tag }} <button
          class="x"
          type="button"
          :aria-label="t('fursona.tags.remove', { tag })"
          @click="remove(tag)"
        >×</button></span>
      </div>
      <div class="bd">
        <input
          v-model="input"
          class="input"
          maxlength="60"
          :placeholder="t('fursona.tags.placeholder')"
          :disabled="tags.length >= max"
          @keydown.enter.prevent="submit"
        >
        <div
          class="hint muted"
          style="font-size:12px;margin-top:6px"
        >
          {{ t('fursona.tags.hint', { n: tags.length, max }) }}
        </div>
      </div>
    </div>
    <div class="card">
      <h2 class="disp">
        {{ t('fursona.tags.suggestionsTitle') }}
      </h2>
      <div class="bd">
        <p
          class="muted"
          style="font-size:12px;margin:0 0 12px"
        >
          {{ t('fursona.tags.suggestionsHint') }}
        </p>
        <div
          class="row"
          style="gap:8px"
        >
          <button
            v-for="s in suggestions.filter(x => !tags.includes(x))"
            :key="s"
            class="pill"
            type="button"
            @click="add(s)"
          >
            {{ s }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
