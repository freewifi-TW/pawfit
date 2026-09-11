<script setup lang="ts">
import type { Media } from '~/types/api'
import { CREDIT_PREFIX, KIND_LABEL, VISIBILITY_LABEL, VISIBILITY_PILL } from '~/utils/labels'

/**
 * 單張圖卡。NSFW 顯示矩陣由後端決定 state（show / blur），
 * 不可見的圖根本不會出現在資料裡；這裡只處理 blur → 點擊解鎖。
 */
const props = withDefaults(defineProps<{
  media: Media
  owner?: boolean
  draggable?: boolean
  showMeta?: boolean
}>(), {
  owner: false,
  draggable: false,
  showMeta: false
})

const emit = defineEmits<{ open: [media: Media], edit: [media: Media] }>()

const unlocked = ref(false)
const isBlur = computed(() => props.media.state === 'blur' && !unlocked.value && !props.owner)
const processing = computed(() => props.media.status === 'processing')
const failed = computed(() => props.media.status === 'failed')
const credit = computed(() => props.media.credit_name?.trim() || '')
</script>

<template>
  <figure
    class="pic"
    :class="{ 'is-blur': isBlur, 'processing': processing || failed }"
  >
    <div class="art">
      <template v-if="processing">
        <div>
          ⏳ 處理中<br><span
            class="muted"
            style="font-weight:400"
          >正在產生展示版與縮圖</span>
        </div>
      </template>
      <template v-else-if="failed">
        <div style="color:var(--danger)">
          ⚠ 處理失敗<br><span
            class="muted"
            style="font-weight:400"
          >{{ media.status_note || '請刪除後重新上傳' }}</span>
        </div>
      </template>
      <template v-else>
        <img
          :src="media.urls.thumb"
          :alt="media.caption || ''"
          loading="lazy"
          :width="media.width || undefined"
          :height="media.height || undefined"
        >
        <button
          v-if="isBlur"
          class="lock"
          type="button"
          @click="unlocked = true"
        >
          <b>NSFW</b>點擊解鎖觀看
        </button>
        <button
          v-else
          class="open"
          type="button"
          :aria-label="`放大檢視 ${media.caption || '圖片'}`"
          @click="emit('open', media)"
        />
      </template>
    </div>

    <span class="kind">{{ KIND_LABEL[media.kind] }}</span>
    <span
      v-if="media.is_nsfw && (owner || showMeta)"
      class="badge-nsfw"
      :style="draggable ? 'right:44px' : ''"
    >NSFW</span>
    <span
      v-if="draggable"
      class="drag"
      title="拖曳排序"
    >⋮⋮</span>

    <figcaption class="cap">
      <div class="t">
        {{ media.caption || '未命名' }}
      </div>
      <div
        v-if="credit"
        class="c"
      >
        {{ CREDIT_PREFIX[media.kind] }}
        <a
          v-if="media.credit_url"
          :href="media.credit_url"
          target="_blank"
          rel="noopener nofollow"
        >{{ credit }}</a>
        <template v-else>
          {{ credit }}
        </template>
      </div>
      <div
        v-if="owner"
        class="ov"
      >
        <span
          class="pill"
          :class="media.visibility_override ? VISIBILITY_PILL[media.visibility_override] : ''"
        >{{ media.visibility_override ? VISIBILITY_LABEL[media.visibility_override] : '繼承隱私' }}</span>
        <span
          class="pill"
          :class="media.is_nsfw ? 'accent' : ''"
        >{{ media.is_nsfw ? 'NSFW' : 'SFW' }}</span>
        <span class="sp" />
        <button
          class="btn sm ghost"
          type="button"
          @click="emit('edit', media)"
        >
          編輯
        </button>
      </div>
    </figcaption>
  </figure>
</template>
