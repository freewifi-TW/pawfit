<script setup lang="ts">
import type { PaletteEntry } from '~/types/api'

/** 色票編輯（FR-2.2）：hex、名稱、部位備註、拖曳排序、一鍵複製。 */
const palette = defineModel<PaletteEntry[]>({ default: () => [] })
const props = withDefaults(defineProps<{ max?: number }>(), { max: 24 })
const notify = useNotify()

function add() {
  if (palette.value.length >= props.max) return
  palette.value = [...palette.value, { hex: '#FF7A59', name: '', note: '' }]
}
function remove(i: number) {
  palette.value = palette.value.filter((_, idx) => idx !== i)
}
function update(i: number, patch: Partial<PaletteEntry>) {
  palette.value = palette.value.map((p, idx) => (idx === i ? { ...p, ...patch } : p))
}
function onHexInput(i: number, v: string) {
  update(i, { hex: v.toUpperCase() })
}
function onHexText(i: number, v: string) {
  let s = v.trim()
  if (!s.startsWith('#')) s = `#${s}`
  if (/^#[0-9a-fA-F]{6}$/.test(s)) update(i, { hex: s.toUpperCase() })
}

async function copyAll() {
  const text = palette.value.map(p => `${p.hex}${p.name ? ` ${p.name}` : ''}${p.note ? `（${p.note}）` : ''}`).join('\n')
  try {
    await navigator.clipboard.writeText(text)
    notify.ok(`已複製 ${palette.value.length} 筆色碼`)
  } catch {
    notify.err('無法存取剪貼簿')
  }
}

// 拖曳排序
const dragIdx = ref<number | null>(null)
function drop(to: number) {
  const from = dragIdx.value
  dragIdx.value = null
  if (from === null || from === to) return
  const next = [...palette.value]
  const [moved] = next.splice(from, 1)
  next.splice(to, 0, moved!)
  palette.value = next
}
</script>

<template>
  <div class="palette-ed">
    <div
      v-for="(p, i) in palette"
      :key="i"
      class="prow"
      draggable="true"
      @dragstart="dragIdx = i"
      @dragover.prevent
      @drop.prevent="drop(i)"
    >
      <span
        class="handle"
        title="拖曳排序"
      >⋮⋮</span>
      <input
        type="color"
        :value="p.hex"
        :aria-label="`顏色 ${i + 1}`"
        @input="onHexInput(i, ($event.target as HTMLInputElement).value)"
      >
      <input
        type="text"
        :value="p.name"
        maxlength="40"
        placeholder="名稱（如：主毛色）"
        @input="update(i, { name: ($event.target as HTMLInputElement).value })"
      >
      <input
        type="text"
        class="note"
        :value="p.note"
        maxlength="80"
        placeholder="部位備註"
        @input="update(i, { note: ($event.target as HTMLInputElement).value })"
      >
      <input
        type="text"
        class="mono hex"
        :value="p.hex"
        maxlength="7"
        style="width:76px;color:var(--ink-2)"
        aria-label="色碼"
        @change="onHexText(i, ($event.target as HTMLInputElement).value)"
      >
      <button
        class="btn sm ghost"
        type="button"
        aria-label="移除"
        @click="remove(i)"
      >
        ✕
      </button>
    </div>
    <div
      v-if="!palette.length"
      class="empty"
    >
      還沒有色票。加入主毛色、腹毛、眼睛…讓繪師一鍵複製。
    </div>
    <div class="row">
      <button
        class="btn sm"
        type="button"
        :disabled="palette.length >= max"
        @click="add"
      >
        ＋ 新增顏色
      </button>
      <button
        class="btn sm ghost"
        type="button"
        :disabled="!palette.length"
        @click="copyAll"
      >
        複製全部色碼
      </button>
      <span class="sp" />
      <span
        class="muted"
        style="font-size:12px"
      >{{ palette.length }} / {{ max }} 色</span>
    </div>
  </div>
</template>
