<script setup lang="ts">
/**
 * 嵌入程式碼產生器（FR-7，設定頁「開放與嵌入」）：
 * iframe 卡片、Markdown／HTML 的 SVG 色票卡、公開 API 網址。尺寸表與 API 的 config('pawfit.embed.sizes') 一致。
 */
const props = defineProps<{ slug: string, name: string }>()

const { t } = useI18n()
const notify = useNotify()
const config = useRuntimeConfig()

const EMBED_SIZES = { sm: [320, 200], md: [480, 320], lg: [640, 420] } as const
type Size = keyof typeof EMBED_SIZES

const tab = ref<'iframe' | 'svg' | 'api'>('iframe')
const size = ref<Size>('md')
const theme = ref<'auto' | 'light' | 'dark'>('auto')
const svgTheme = ref<'light' | 'dark'>('light')
const svgLayout = ref<'row' | 'grid'>('row')
const svgFormat = ref<'markdown' | 'html'>('markdown')

const site = computed(() => config.public.siteUrl)
const cardUrl = computed(() => `${site.value}/embed/${props.slug}?theme=${theme.value}&size=${size.value}`)
const svgUrl = computed(() => `${site.value}/embed/${props.slug}/palette.svg?theme=${svgTheme.value}&layout=${svgLayout.value}`)
const apiUrl = computed(() => `${site.value}/api/v1/public/fursonas/${props.slug}`)
const shareUrl = computed(() => `${site.value}/s/${props.slug}`)

const iframeCode = computed(() => {
  const [w, h] = EMBED_SIZES[size.value]
  return `<iframe src="${cardUrl.value}" width="${w}" height="${h}" style="border:0;border-radius:20px;max-width:100%" loading="lazy" title="${props.name} · Pawfit" referrerpolicy="strict-origin"></iframe>`
})
const svgCode = computed(() => svgFormat.value === 'markdown'
  ? `[![${props.name} palette](${svgUrl.value})](${shareUrl.value})`
  : `<a href="${shareUrl.value}"><img src="${svgUrl.value}" alt="${props.name} palette"></a>`)
const apiCode = computed(() => `curl ${apiUrl.value}`)

const code = computed(() => tab.value === 'iframe' ? iframeCode.value : tab.value === 'svg' ? svgCode.value : apiCode.value)

async function copy() {
  try {
    await navigator.clipboard.writeText(code.value)
    notify.ok(t('settings.embed.copied'))
  } catch {
    notify.err(t('settings.embed.clipboardError'))
  }
}
</script>

<template>
  <div class="embedgen">
    <div class="row">
      <button
        v-for="k in (['iframe', 'svg', 'api'] as const)"
        :key="k"
        class="btn sm"
        :class="{ primary: tab === k }"
        type="button"
        :aria-pressed="tab === k"
        @click="tab = k"
      >
        {{ t(`settings.embed.tabs.${k}`) }}
      </button>
    </div>

    <div
      v-if="tab === 'iframe'"
      class="row"
    >
      <label class="opt">
        <span>{{ t('settings.embed.size') }}</span>
        <select
          v-model="size"
          class="input"
        >
          <option
            v-for="(dim, k) in EMBED_SIZES"
            :key="k"
            :value="k"
          >{{ t(`settings.embed.sizes.${k}`) }} · {{ dim[0] }}×{{ dim[1] }}</option>
        </select>
      </label>
      <label class="opt">
        <span>{{ t('settings.embed.theme') }}</span>
        <select
          v-model="theme"
          class="input"
        >
          <option value="auto">{{ t('settings.embed.themes.auto') }}</option>
          <option value="light">{{ t('settings.embed.themes.light') }}</option>
          <option value="dark">{{ t('settings.embed.themes.dark') }}</option>
        </select>
      </label>
    </div>

    <div
      v-else-if="tab === 'svg'"
      class="row"
    >
      <label class="opt">
        <span>{{ t('settings.embed.format') }}</span>
        <select
          v-model="svgFormat"
          class="input"
        >
          <option value="markdown">Markdown</option>
          <option value="html">HTML</option>
        </select>
      </label>
      <label class="opt">
        <span>{{ t('settings.embed.theme') }}</span>
        <select
          v-model="svgTheme"
          class="input"
        >
          <option value="light">{{ t('settings.embed.themes.light') }}</option>
          <option value="dark">{{ t('settings.embed.themes.dark') }}</option>
        </select>
      </label>
      <label class="opt">
        <span>{{ t('settings.embed.layout') }}</span>
        <select
          v-model="svgLayout"
          class="input"
        >
          <option value="row">{{ t('settings.embed.layouts.row') }}</option>
          <option value="grid">{{ t('settings.embed.layouts.grid') }}</option>
        </select>
      </label>
    </div>

    <p
      v-else
      class="muted"
      style="font-size:12px;margin:0"
    >
      {{ t('settings.embed.apiNote') }}
    </p>

    <textarea
      class="input mono"
      rows="3"
      readonly
      :value="code"
      @focus="($event.target as HTMLTextAreaElement).select()"
    />

    <div class="row">
      <button
        class="btn sm primary"
        type="button"
        @click="copy"
      >
        {{ t('settings.embed.copy') }}
      </button>
      <a
        class="btn sm ghost"
        :href="tab === 'svg' ? svgUrl : tab === 'api' ? apiUrl : cardUrl"
        target="_blank"
        rel="noopener"
      >{{ t('settings.embed.preview') }} ↗</a>
    </div>

    <div
      v-if="tab !== 'api'"
      class="preview"
    >
      <iframe
        v-if="tab === 'iframe'"
        :key="cardUrl"
        :src="cardUrl"
        :width="EMBED_SIZES[size][0]"
        :height="EMBED_SIZES[size][1]"
        style="border:0;border-radius:20px;max-width:100%"
        loading="lazy"
        :title="`${name} · Pawfit`"
      />
      <img
        v-else
        :key="svgUrl"
        :src="svgUrl"
        :alt="`${name} palette`"
        style="max-width:100%"
      >
    </div>
  </div>
</template>

<style scoped>
.embedgen { display: grid; gap: 12px; }
.opt { display: grid; gap: 4px; font-size: 12px; font-weight: 700; min-width: 150px; }
.opt .input { padding: 6px 10px; font-size: 13px; }
textarea.input { font-size: 12px; resize: vertical; }
.preview { padding: 14px; border: var(--border) dashed var(--line); border-radius: var(--r-in); overflow: auto; }
</style>
