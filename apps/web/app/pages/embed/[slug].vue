<script setup lang="ts">
import type { PublicFursona, PublicMediaPage } from '~/types/api'
import { paletteGradient } from '~/utils/labels'

/**
 * iframe 卡片（FR-7.4）：GET /embed/:slug?theme=light|dark|auto&size=sm|md|lg
 * - 資料只走公開 API v1（觀看者永遠視同訪客，NSFW 不會出現），SSR 時不轉送任何 cookie
 * - 不含站內導覽、不設 cookie、無登入狀態；frame-ancestors * 由 Caddy 對 /embed/* 設定
 */
definePageMeta({ layout: 'embed' })

const route = useRoute()
const config = useRuntimeConfig()
const { t } = useI18n()
const slug = route.params.slug as string

const theme = (['light', 'dark', 'auto'] as const).find(v => v === route.query.theme) ?? 'auto'
const size = (['sm', 'md', 'lg'] as const).find(v => v === route.query.size) ?? 'md'
const thumbLimit = size === 'sm' ? 0 : 4

const base = import.meta.server ? config.apiInternalBase : config.public.apiBase

const { data, error } = await useAsyncData(`embed-${slug}-${thumbLimit}`, async () => {
  const fursona = await $fetch<PublicFursona>(`${base}/v1/public/fursonas/${encodeURIComponent(slug)}`, { headers: { accept: 'application/json' } })
  const media = thumbLimit > 0
    ? await $fetch<PublicMediaPage>(`${base}/v1/public/fursonas/${encodeURIComponent(slug)}/media`, { headers: { accept: 'application/json' } })
    : null
  return { fursona, media: media?.data.slice(0, thumbLimit) ?? [] }
})
if (error.value || !data.value) {
  throw createError({ statusCode: 404, statusMessage: t('embed.notFound') })
}

const f = computed(() => data.value!.fursona)
const thumbs = computed(() => data.value!.media)
const bio = computed(() => (f.value.bio ?? '').split('\n').map(s => s.trim()).filter(Boolean)[0] ?? '')
const palette = computed(() => f.value.palette.slice(0, size === 'sm' ? 6 : 8))

useSeoMeta({ title: () => f.value.name })
</script>

<template>
  <div
    v-if="data"
    class="emb"
    :class="[`theme-${theme}`, `size-${size}`]"
  >
    <header class="emb-hd">
      <BlobAvatar
        :src="f.avatar_url"
        :gradient="paletteGradient(f.palette)"
        :size="size === 'sm' ? 48 : 64"
        :border="3"
        :alt="f.name"
      />
      <div class="emb-title">
        <b class="disp">{{ f.name }}</b>
        <span v-if="f.species">{{ f.species }}</span>
        <a
          class="emb-owner"
          :href="f.owner.profile_url"
          target="_blank"
          rel="noopener"
        >@{{ f.owner.pawfit_id }}</a>
      </div>
      <a
        class="emb-logo disp"
        :href="f.share_url"
        target="_blank"
        rel="noopener"
        :title="t('embed.viewOnPawfit')"
      >pawfit</a>
    </header>

    <p
      v-if="bio && size !== 'sm'"
      class="emb-bio"
    >
      {{ bio }}
    </p>

    <div
      v-if="palette.length"
      class="emb-palette"
    >
      <span
        v-for="(p, i) in palette"
        :key="i"
        class="emb-swatch"
        :title="`${p.name || ''} ${p.hex}`.trim()"
      >
        <i :style="`background:${p.hex}`" />
        <small class="mono">{{ p.hex }}</small>
      </span>
    </div>

    <div
      v-if="thumbs.length"
      class="emb-thumbs"
    >
      <a
        v-for="m in thumbs"
        :key="m.id"
        :href="f.share_url"
        target="_blank"
        rel="noopener"
      >
        <img
          :src="m.thumb_url"
          :alt="m.caption || f.name"
          loading="lazy"
        >
      </a>
    </div>

    <footer class="emb-ft">
      <span class="muted">{{ t('embed.mediaCount', f.media_count) }}</span>
      <span class="sp" />
      <a
        class="btn sm primary"
        :href="f.share_url"
        target="_blank"
        rel="noopener"
      >{{ t('embed.viewOnPawfit') }} ↗</a>
    </footer>
  </div>
</template>

<style scoped>
.emb {
  box-sizing: border-box;
  height: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  background: var(--paper);
  color: var(--ink);
  border: var(--border) solid var(--line);
  border-radius: 20px;
  overflow: hidden;
  font: 14px/1.5 var(--font-body), system-ui, sans-serif;
}
.emb.theme-dark { color-scheme: dark; }
.emb-hd { display: flex; align-items: center; gap: 12px; min-width: 0; }
.emb-title { display: grid; min-width: 0; line-height: 1.25; }
.emb-title b { font-size: 18px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.emb-title span { font-size: 12px; color: var(--ink-2); }
.emb-owner { font-size: 12px; color: var(--ink-3); text-decoration: none; }
.emb-owner:hover { color: var(--accent); }
.emb-logo { margin-left: auto; align-self: flex-start; font-size: 13px; color: var(--accent); text-decoration: none; letter-spacing: .02em; }
.emb-bio { margin: 0; font-size: 13px; color: var(--ink-2); display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.emb-palette { display: flex; gap: 8px; flex-wrap: wrap; }
.emb-swatch { display: grid; justify-items: center; gap: 2px; }
.emb-swatch i { display: block; width: 34px; height: 34px; border-radius: 10px; border: 2px solid var(--line); }
.emb-swatch small { font-size: 9px; color: var(--ink-3); }
.emb-thumbs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; flex: 1; min-height: 0; }
.emb-thumbs a { display: block; border-radius: 12px; overflow: hidden; border: 2px solid var(--line); background: var(--paper-2); min-height: 0; }
.emb-thumbs img { width: 100%; height: 100%; object-fit: cover; display: block; }
.emb-ft { display: flex; align-items: center; gap: 10px; font-size: 12px; margin-top: auto; }
.size-sm { gap: 8px; padding: 12px 14px; }
.size-sm .emb-swatch i { width: 26px; height: 26px; border-radius: 8px; }
.size-sm .emb-swatch small { display: none; }
.size-lg .emb-thumbs { grid-template-columns: repeat(4, 1fr); }
</style>
