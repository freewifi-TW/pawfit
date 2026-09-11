<script setup lang="ts">
import type { Media, MediaKind } from '~/types/api'

/** 圖庫格線：分類篩選、燈箱；擁有者模式支援拖曳排序與編輯。 */
const props = withDefaults(defineProps<{
  media: Media[]
  owner?: boolean
  hiddenNsfw?: number
}>(), {
  owner: false,
  hiddenNsfw: 0
})

const emit = defineEmits<{ reorder: [ids: string[]], edit: [media: Media] }>()

const { t } = useI18n()
const { kindLabel } = useLabels()

const filter = ref<'all' | MediaKind>('all')
const filters = computed<Array<{ value: 'all' | MediaKind, label: string }>>(() => [
  { value: 'all', label: t('media.grid.filterAll') },
  { value: 'art2d', label: kindLabel('art2d') },
  { value: 'model3d', label: kindLabel('model3d') },
  { value: 'photo', label: kindLabel('photo') }
])

const shown = computed(() => filter.value === 'all' ? props.media : props.media.filter(m => m.kind === filter.value))
const lightbox = ref<Media | null>(null)

// 拖曳排序（只在「全部」且擁有者模式）
const canDrag = computed(() => props.owner && filter.value === 'all')
const dragId = ref<string | null>(null)
const overId = ref<string | null>(null)

function onDragStart(m: Media, e: DragEvent) {
  if (!canDrag.value) return
  dragId.value = m.id
  e.dataTransfer?.setData('text/plain', m.id)
  if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move'
}
function onDrop(target: Media) {
  const from = dragId.value
  dragId.value = null
  overId.value = null
  if (!from || from === target.id) return
  const ids = props.media.map(m => m.id)
  const fromIdx = ids.indexOf(from)
  const toIdx = ids.indexOf(target.id)
  ids.splice(fromIdx, 1)
  ids.splice(toIdx, 0, from)
  emit('reorder', ids)
}
</script>

<template>
  <div>
    <div class="gal-tools">
      <slot name="tools" />
      <div class="seg">
        <button
          v-for="f in filters"
          :key="f.value"
          type="button"
          :class="{ on: filter === f.value }"
          @click="filter = f.value"
        >
          {{ f.label }}
        </button>
      </div>
      <span class="count">
        {{ t('media.grid.count', shown.length) }}
        <template v-if="owner"> · {{ t('media.grid.ownerHint') }}</template>
      </span>
    </div>

    <p
      v-if="!owner && hiddenNsfw > 0"
      class="visitor-note"
    >
      {{ t('media.grid.hiddenNsfw', hiddenNsfw) }}<slot name="nsfw-hint">
        {{ t('media.grid.nsfwHint') }}
      </slot>
    </p>

    <div
      v-if="shown.length"
      class="gallery"
    >
      <MediaCard
        v-for="m in shown"
        :key="m.id"
        :media="m"
        :owner="owner"
        :draggable="canDrag"
        :class="{ 'dragging': dragId === m.id, 'drop-target': overId === m.id && dragId !== m.id }"
        @dragstart="onDragStart(m, $event)"
        @dragover.prevent="canDrag && (overId = m.id)"
        @dragleave="overId === m.id && (overId = null)"
        @drop.prevent="canDrag && onDrop(m)"
        @dragend="dragId = null; overId = null"
        @open="lightbox = $event"
        @edit="emit('edit', $event)"
      />
    </div>
    <div
      v-else
      class="empty"
      style="margin:16px 22px 22px"
    >
      <slot name="empty">
        {{ t('media.grid.empty') }}
      </slot>
    </div>

    <MediaLightbox v-model="lightbox" />
  </div>
</template>
