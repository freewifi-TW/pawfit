<script setup lang="ts">
import type { CommissionPage } from '~/types/api'

/**
 * 委託需求單公開頁（FR-6.4）：SSR + OG；描述文字可切換語言並一鍵複製；合成圖；參考圖原尺寸展示版。
 * NSFW 需求單對訪客整份不顯示（後端回 404）。
 */
const route = useRoute()
const api = useApi()
const config = useRuntimeConfig()
const notify = useNotify()
const { t, locale } = useI18n()
const { formatDate } = useLabels()
const slug = route.params.slug as string

const { data: page, error } = await useAsyncData(`commission-${slug}`, () => api<CommissionPage>(`/commission/${encodeURIComponent(slug)}`))
if (error.value || !page.value) {
  throw createError({ statusCode: 404, statusMessage: t('commission.page.notFound') })
}

const kit = computed(() => page.value!.kit)
const snap = computed(() => kit.value.snapshot)
const briefLocales = computed(() => Object.keys(kit.value.brief_text))
const briefLocale = ref(briefLocales.value.includes(locale.value) ? locale.value : (briefLocales.value[0] ?? 'zh-TW'))
const brief = computed(() => kit.value.brief_text[briefLocale.value] ?? '')
const sheetUrl = computed(() => kit.value.sheet_url)
const title = computed(() => t('commission.page.title', { name: snap.value.fursona.name }))
const ogImage = computed(() => page.value!.og_image_url ? `${config.public.siteUrl}${page.value!.og_image_url}` : undefined)
const requestRows = computed(() => (['composition', 'scene', 'size', 'usage', 'budget', 'deadline', 'notes'] as const)
  .filter(k => !!snap.value.request[k])
  .map(k => ({ key: k, label: t(`commission.fields.${k}`), value: snap.value.request[k] as string })))

useSeoMeta({
  title,
  description: () => t('commission.page.seoDescription', { name: snap.value.fursona.name, media: kit.value.media_count, palette: snap.value.fursona.palette.length }),
  ogTitle: () => `${title.value} — Pawfit`,
  ogImage,
  ogUrl: () => kit.value.url,
  twitterCard: 'summary_large_image',
  twitterImage: ogImage,
  robots: 'noindex, nofollow'
})

async function copyBrief() {
  try {
    await navigator.clipboard.writeText(brief.value)
    notify.ok(t('commission.page.copied'))
  } catch {
    notify.err(t('commission.page.clipboardError'))
  }
}
</script>

<template>
  <section
    v-if="page"
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <span class="pill accent">{{ t('commission.page.badge') }}</span>
        <h1
          class="disp"
          style="margin-top:8px"
        >
          {{ snap.fursona.name }}<small v-if="snap.fursona.species"> · {{ snap.fursona.species }}</small>
        </h1>
        <p class="sub">
          {{ t('commission.page.by', { id: page.owner.pawfit_id, date: formatDate(kit.created_at) }) }}
          <template v-if="page.fursona.share_url">
            · <a
              class="link"
              :href="page.fursona.share_url"
            >{{ t('commission.page.fullSheet') }}</a>
          </template>
        </p>
      </div>
      <NuxtLink
        v-if="page.is_owner"
        class="btn sm"
        :to="`/fursona/${page.fursona.id}#commission`"
      >
        {{ t('commission.page.manage') }}
      </NuxtLink>
    </div>

    <div
      v-if="page.state === 'blur'"
      class="visitor-note"
      style="margin-bottom:16px"
    >
      {{ t('commission.page.nsfwNote') }}
    </div>

    <div class="board">
      <aside class="stack">
        <div class="card">
          <div class="hd">
            <span class="disp">{{ t('commission.page.briefTitle') }}</span>
            <em>
              <button
                v-for="l in briefLocales"
                :key="l"
                class="btn sm"
                :class="{ primary: briefLocale === l }"
                type="button"
                style="margin-left:6px"
                @click="briefLocale = l"
              >
                {{ t(`commission.locales.${l}`, l) }}
              </button>
            </em>
          </div>
          <div
            class="bd stack"
            style="gap:10px"
          >
            <pre class="brief mono">{{ brief }}</pre>
            <div class="row">
              <button
                class="btn primary"
                type="button"
                @click="copyBrief"
              >
                {{ t('commission.page.copy') }}
              </button>
              <span
                class="muted"
                style="font-size:12px"
              >{{ t('commission.page.copyHint') }}</span>
            </div>
          </div>
        </div>

        <div
          v-if="requestRows.length"
          class="card"
        >
          <h2 class="disp">
            {{ t('commission.page.requestTitle') }}
          </h2>
          <div class="bd">
            <dl class="req">
              <template
                v-for="r in requestRows"
                :key="r.key"
              >
                <dt>{{ r.label }}</dt>
                <dd>{{ r.value }}</dd>
              </template>
            </dl>
          </div>
        </div>

        <div
          v-if="snap.fursona.palette.length"
          class="card"
        >
          <div class="hd">
            <span class="disp">{{ t('commission.page.paletteTitle') }}</span><em>{{ t('commission.page.paletteHint') }}</em>
          </div>
          <PaletteChips :palette="snap.fursona.palette" />
        </div>
      </aside>

      <section class="stack">
        <div class="card">
          <div class="hd">
            <span class="disp">{{ t('commission.page.sheetTitle') }}</span>
            <em v-if="kit.status === 'processing'">{{ t('commission.page.sheetProcessing') }}</em>
          </div>
          <div class="bd">
            <a
              v-if="sheetUrl"
              :href="sheetUrl"
              target="_blank"
              rel="noopener"
            >
              <img
                class="sheet"
                :src="sheetUrl"
                :alt="t('commission.page.sheetAlt', { name: snap.fursona.name })"
              >
            </a>
            <div
              v-else
              class="empty"
            >
              {{ kit.status === 'failed' ? t('commission.page.sheetFailed') : t('commission.page.sheetProcessing') }}
            </div>
          </div>
        </div>

        <div class="card">
          <h2 class="disp">
            {{ t('commission.page.referencesTitle', page.media.length) }}
          </h2>
          <div class="bd">
            <GalleryGrid
              :media="page.media"
              :owner="page.is_owner"
            >
              <template #empty>
                {{ t('commission.page.referencesEmpty') }}
              </template>
            </GalleryGrid>
          </div>
        </div>
      </section>
    </div>

    <p
      class="muted"
      style="font-size:12px;margin-top:24px"
    >
      {{ t('commission.page.footer') }}
    </p>
  </section>
</template>

<style scoped>
.brief { margin: 0; padding: 14px 16px; background: var(--paper-2); border: var(--border) solid var(--line); border-radius: var(--r-in); font-size: 12.5px; line-height: 1.6; white-space: pre-wrap; word-break: break-word; max-height: 520px; overflow: auto; }
.req { display: grid; grid-template-columns: max-content 1fr; gap: 8px 16px; margin: 0; font-size: 14px; }
.req dt { font-weight: 700; color: var(--ink-2); }
.req dd { margin: 0; white-space: pre-wrap; }
.sheet { display: block; width: 100%; border-radius: var(--r-in); border: var(--border) solid var(--line); }
</style>
