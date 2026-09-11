<script setup lang="ts">
import type { Media } from '~/types/api'
import { VISIBILITY_PILL } from '~/utils/labels'

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

const { t } = useI18n()
const { creditPrefix, kindLabel, visibilityLabel } = useLabels()

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
          ⏳ {{ t('media.card.processing') }}<br><span
            class="muted"
            style="font-weight:400"
          >{{ t('media.card.processingHint') }}</span>
        </div>
      </template>
      <template v-else-if="failed">
        <div style="color:var(--danger)">
          ⚠ {{ t('media.card.failed') }}<br><span
            class="muted"
            style="font-weight:400"
          >{{ media.status_note || t('media.card.failedHint') }}</span>
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
          <b>NSFW</b>{{ t('media.card.unlock') }}
        </button>
        <button
          v-else
          class="open"
          type="button"
          :aria-label="t('media.card.openAria', { name: media.caption || t('media.common.image') })"
          @click="emit('open', media)"
        />
      </template>
    </div>

    <span class="kind">{{ kindLabel(media.kind) }}</span>
    <span
      v-if="media.is_nsfw && (owner || showMeta)"
      class="badge-nsfw"
      :style="draggable ? 'right:44px' : ''"
    >NSFW</span>
    <span
      v-if="draggable"
      class="drag"
      :title="t('media.card.dragSort')"
    >⋮⋮</span>

    <figcaption class="cap">
      <div class="t">
        {{ media.caption || t('media.common.untitled') }}
      </div>
      <div
        v-if="credit"
        class="c"
      >
        {{ creditPrefix(media.kind) }}
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
        >{{ media.visibility_override ? visibilityLabel(media.visibility_override) : t('media.card.inheritVisibility') }}</span>
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
          {{ t('media.card.edit') }}
        </button>
      </div>
    </figcaption>
  </figure>
</template>
