<script setup lang="ts">
import { tagStyle } from '~/utils/labels'

/** 獸設標籤（FR-2.3）：Enter 新增、點 × 移除、常用標籤一鍵加入。 */
const tags = defineModel<string[]>({ default: () => [] })
const props = withDefaults(defineProps<{ max?: number }>(), { max: 30 })

const input = ref('')
const suggestions = ['犬科', '貓科', '龍', '鳥類', '有翼', '多肉', '壯碩', '纖瘦', '標準體型', '成年', '大尾', '異色瞳', '機械義肢']

function add(raw: string) {
  const t = raw.trim().replace(/\s+/g, ' ').slice(0, 20)
  if (!t || tags.value.includes(t) || tags.value.length >= props.max) return
  tags.value = [...tags.value, t]
}
function submit() {
  input.value.split(/[,，]/).forEach(add)
  input.value = ''
}
function remove(t: string) {
  tags.value = tags.value.filter(x => x !== t)
}
</script>

<template>
  <div class="ed-grid">
    <div class="card">
      <div class="hd">
        <span class="disp">獸設標籤</span><em>發文時會自動帶入（Phase 2）</em>
      </div>
      <div
        v-if="tags.length"
        class="tagbox"
      >
        <span
          v-for="(t, i) in tags"
          :key="t"
          class="tag"
          :class="tagStyle(i).class"
          :style="tagStyle(i).style"
        >{{ t }} <button
          class="x"
          type="button"
          :aria-label="`移除 ${t}`"
          @click="remove(t)"
        >×</button></span>
      </div>
      <div class="bd">
        <input
          v-model="input"
          class="input"
          maxlength="60"
          placeholder="輸入標籤後按 Enter，例如：長尾、異色瞳"
          :disabled="tags.length >= max"
          @keydown.enter.prevent="submit"
        >
        <div
          class="hint muted"
          style="font-size:12px;margin-top:6px"
        >
          用來分類與被搜尋。物種、體型、年齡段建議都填。{{ tags.length }} / {{ max }}
        </div>
      </div>
    </div>
    <div class="card">
      <h2 class="disp">
        常用標籤
      </h2>
      <div class="bd">
        <p
          class="muted"
          style="font-size:12px;margin:0 0 12px"
        >
          點一下加入
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
