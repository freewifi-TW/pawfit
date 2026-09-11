<script setup lang="ts">
import type { Fursona } from '~/types/api'
import { paletteGradient, VISIBILITY_PILL } from '~/utils/labels'

/** 獸設卡：儀表板（擁有者）與個人主頁（公開）共用。 */
withDefaults(defineProps<{
  fursona: Fursona
  owner?: boolean
}>(), {
  owner: false
})

const { t } = useI18n()
const { visibilityLabel } = useLabels()
</script>

<template>
  <article class="card sona">
    <div
      class="cover"
      :style="fursona.cover_url ? '' : `background:${paletteGradient(fursona.palette)}`"
    >
      <img
        v-if="fursona.cover_url"
        :src="fursona.cover_url"
        :alt="fursona.name"
        loading="lazy"
      >
      <span
        v-if="fursona.is_representative"
        class="badge-nsfw"
        style="background:var(--butter);color:var(--ink)"
      >★ {{ t('media.fursonaCard.representative') }}</span>
      <span
        v-else-if="fursona.is_nsfw && owner"
        class="badge-nsfw"
      >NSFW</span>
      <BlobAvatar
        :src="fursona.avatar_url"
        :gradient="paletteGradient(fursona.palette)"
        :size="72"
        :alt="fursona.name"
      />
    </div>
    <div class="bd">
      <h3 class="disp">
        {{ fursona.name }} <small v-if="fursona.species">{{ fursona.species }}</small>
      </h3>
      <div class="meta">
        <span
          v-if="owner"
          class="pill"
          :class="VISIBILITY_PILL[fursona.visibility]"
        >{{ visibilityLabel(fursona.visibility) }}</span>
        <span
          v-if="owner && fursona.removed_at"
          class="pill danger"
        >{{ t('media.fursonaCard.removed') }}</span>
        <span class="pill">{{ t('media.fursonaCard.mediaCount', fursona.media_count ?? 0) }}</span>
        <span class="pill">{{ t('media.fursonaCard.paletteCount', fursona.palette_count) }}</span>
      </div>
      <div class="acts">
        <template v-if="owner">
          <NuxtLink
            class="btn sm"
            :to="`/fursona/${fursona.id}`"
          >
            {{ t('media.fursonaCard.edit') }}
          </NuxtLink>
          <NuxtLink
            v-if="fursona.share_link && fursona.visibility !== 'private'"
            class="btn sm ghost"
            :to="`/s/${fursona.share_link.slug}`"
          >
            {{ t('media.fursonaCard.sharePage') }} ↗
          </NuxtLink>
          <button
            v-else
            class="btn sm ghost"
            type="button"
            disabled
          >
            {{ fursona.visibility === 'private' ? t('media.fursonaCard.privateDisabled') : t('media.fursonaCard.noLink') }}
          </button>
        </template>
        <template v-else>
          <slot name="actions" />
        </template>
      </div>
    </div>
  </article>
</template>
