<script setup lang="ts">
import type { Fursona, ShareLink } from '~/types/api'

/** 分享連結管理（FR-4）：產生、重新生成（舊連結失效）、停用、浮水印開關、OG 預覽。 */
const props = defineProps<{ fursona: Fursona }>()
const emit = defineEmits<{ change: [link: ShareLink | null] }>()

const { t } = useI18n()
const api = useApi()
const notify = useNotify()
const config = useRuntimeConfig()

const link = computed(() => props.fursona.share_link ?? null)
const busy = ref(false)
const confirmRegen = ref(false)
const confirmRevoke = ref(false)

const displayUrl = computed(() => link.value ? link.value.url.replace(/^https?:\/\//, '') : '')
const isPrivate = computed(() => props.fursona.visibility === 'private')
const ogImage = computed(() => props.fursona.cover_url)
const ogDescription = computed(() => {
  const bio = (props.fursona.bio ?? '').split('\n')[0]?.trim() ?? ''
  const parts = [bio, t('fursona.share.ogStats', { palette: props.fursona.palette_count, media: props.fursona.media_count ?? 0 })].filter(Boolean)
  return `${parts.join(' ')} — Pawfit`
})

async function create(watermark?: boolean) {
  busy.value = true
  try {
    const created = await api<ShareLink>(`/fursonas/${props.fursona.id}/share-link`, {
      method: 'POST',
      body: watermark === undefined ? {} : { watermark }
    })
    emit('change', created)
    notify.ok(link.value ? t('fursona.share.notify.regenerated') : t('fursona.share.notify.created'))
    confirmRegen.value = false
  } catch (e) {
    notify.err(t('fursona.share.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function toggleWatermark(v: boolean) {
  if (!link.value) return
  busy.value = true
  try {
    const updated = await api<ShareLink>(`/share-links/${link.value.id}`, { method: 'PATCH', body: { watermark: v } })
    emit('change', updated)
    notify.ok(v ? t('fursona.share.notify.watermarkOn') : t('fursona.share.notify.watermarkOff'))
  } catch (e) {
    notify.err(t('fursona.share.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function revoke() {
  if (!link.value) return
  busy.value = true
  try {
    await api(`/share-links/${link.value.id}`, { method: 'DELETE' })
    emit('change', null)
    notify.ok(t('fursona.share.notify.revoked'))
    confirmRevoke.value = false
  } catch (e) {
    notify.err(t('fursona.share.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function copy() {
  if (!link.value) return
  try {
    await navigator.clipboard.writeText(link.value.url)
    notify.ok(t('fursona.share.notify.copied'))
  } catch {
    notify.err(t('fursona.share.notify.clipboardError'))
  }
}

const absoluteOg = computed(() => ogImage.value ? `${config.public.siteUrl}${ogImage.value}` : '')
</script>

<template>
  <div class="card">
    <h2 class="disp">
      {{ t('fursona.share.title') }}
    </h2>
    <div class="sharebox">
      <p
        v-if="isPrivate"
        class="visitor-note"
        style="margin:0"
      >
        {{ t('fursona.share.privateNotice') }}
      </p>

      <template v-if="link">
        <div class="linkrow">
          <input
            class="input mono"
            :value="displayUrl"
            readonly
            :aria-label="t('fursona.share.urlLabel')"
            @focus="($event.target as HTMLInputElement).select()"
          >
          <button
            class="btn"
            type="button"
            @click="copy"
          >
            {{ t('fursona.share.copy') }}
          </button>
          <NuxtLink
            class="btn ghost"
            :to="`/s/${link.slug}`"
            target="_blank"
          >
            {{ t('fursona.share.open') }}
          </NuxtLink>
        </div>
        <label class="switch">
          <input
            type="checkbox"
            :checked="link.watermark"
            :disabled="busy"
            @change="toggleWatermark(($event.target as HTMLInputElement).checked)"
          > {{ t('fursona.share.watermark') }}
        </label>
        <div class="row">
          <template v-if="!confirmRegen">
            <button
              class="btn sm"
              type="button"
              :disabled="busy"
              @click="confirmRegen = true"
            >
              {{ t('fursona.share.regenerate') }}
            </button>
          </template>
          <template v-else>
            <span
              class="muted"
              style="font-size:12px"
            >{{ t('fursona.share.regenerateAsk') }}</span>
            <button
              class="btn sm primary"
              type="button"
              :disabled="busy"
              @click="create()"
            >
              {{ t('fursona.share.regenerateConfirm') }}
            </button>
            <button
              class="btn sm ghost"
              type="button"
              @click="confirmRegen = false"
            >
              {{ t('fursona.actions.cancel') }}
            </button>
          </template>
          <template v-if="!confirmRevoke && !confirmRegen">
            <button
              class="btn sm danger"
              type="button"
              :disabled="busy"
              @click="confirmRevoke = true"
            >
              {{ t('fursona.share.revoke') }}
            </button>
          </template>
          <template v-else-if="confirmRevoke">
            <button
              class="btn sm danger"
              type="button"
              :disabled="busy"
              @click="revoke"
            >
              {{ t('fursona.share.revokeConfirm') }}
            </button>
            <button
              class="btn sm ghost"
              type="button"
              @click="confirmRevoke = false"
            >
              {{ t('fursona.actions.cancel') }}
            </button>
          </template>
          <span class="sp" />
          <span
            class="muted"
            style="font-size:12px"
          >{{ t('fursona.share.regenerateNote') }}</span>
        </div>
        <div>
          <div
            class="muted"
            style="font-size:12px;margin-bottom:8px"
          >
            {{ t('fursona.share.ogTitle') }}
          </div>
          <div class="og">
            <div class="img">
              <img
                v-if="ogImage"
                :src="ogImage"
                alt=""
              >
            </div>
            <div class="t">
              <b>{{ fursona.name }}<template v-if="fursona.species"> · {{ fursona.species }}</template></b>
              <span>{{ ogDescription }}</span>
            </div>
          </div>
          <p
            v-if="!ogImage"
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            {{ t('fursona.share.ogNoImage') }}
          </p>
          <p
            v-else-if="absoluteOg"
            class="muted mono"
            style="font-size:11px;margin:8px 0 0;word-break:break-all"
          >
            {{ absoluteOg }}
          </p>
        </div>
      </template>

      <template v-else>
        <p
          class="sub"
          style="margin:0"
        >
          {{ t('fursona.share.empty') }}
        </p>
        <div class="row">
          <button
            class="btn primary"
            type="button"
            :disabled="busy"
            @click="create(false)"
          >
            {{ t('fursona.share.create') }}
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
