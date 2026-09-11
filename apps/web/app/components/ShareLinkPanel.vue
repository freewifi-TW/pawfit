<script setup lang="ts">
import type { Fursona, ShareLink } from '~/types/api'

/** 分享連結管理（FR-4）：產生、重新生成（舊連結失效）、停用、浮水印開關、OG 預覽。 */
const props = defineProps<{ fursona: Fursona }>()
const emit = defineEmits<{ change: [link: ShareLink | null] }>()

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
  const parts = [bio, `${props.fursona.palette_count} 色色票 · ${props.fursona.media_count ?? 0} 張設定圖`].filter(Boolean)
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
    notify.ok(link.value ? '已重新生成連結，舊網址立即失效' : '已產生分享連結')
    confirmRegen.value = false
  } catch (e) {
    notify.err('操作失敗', apiError(e).message)
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
    notify.ok(v ? '已開啟浮水印，正在替既有圖片產生浮水印版' : '已關閉浮水印')
  } catch (e) {
    notify.err('操作失敗', apiError(e).message)
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
    notify.ok('連結已停用，舊網址立即失效')
    confirmRevoke.value = false
  } catch (e) {
    notify.err('操作失敗', apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function copy() {
  if (!link.value) return
  try {
    await navigator.clipboard.writeText(link.value.url)
    notify.ok('已複製分享連結')
  } catch {
    notify.err('無法存取剪貼簿')
  }
}

const absoluteOg = computed(() => ogImage.value ? `${config.public.siteUrl}${ogImage.value}` : '')
</script>

<template>
  <div class="card">
    <h2 class="disp">
      分享連結
    </h2>
    <div class="sharebox">
      <p
        v-if="isPrivate"
        class="visitor-note"
        style="margin:0"
      >
        這隻獸設目前是「私人」，分享連結自動停用；改回公開或連結可見後就會恢復。
      </p>

      <template v-if="link">
        <div class="linkrow">
          <input
            class="input mono"
            :value="displayUrl"
            readonly
            aria-label="分享連結"
            @focus="($event.target as HTMLInputElement).select()"
          >
          <button
            class="btn"
            type="button"
            @click="copy"
          >
            複製
          </button>
          <NuxtLink
            class="btn ghost"
            :to="`/s/${link.slug}`"
            target="_blank"
          >
            開啟 ↗
          </NuxtLink>
        </div>
        <label class="switch">
          <input
            type="checkbox"
            :checked="link.watermark"
            :disabled="busy"
            @change="toggleWatermark(($event.target as HTMLInputElement).checked)"
          > 分享頁使用浮水印版圖檔
        </label>
        <div class="row">
          <template v-if="!confirmRegen">
            <button
              class="btn sm"
              type="button"
              :disabled="busy"
              @click="confirmRegen = true"
            >
              重新生成連結
            </button>
          </template>
          <template v-else>
            <span
              class="muted"
              style="font-size:12px"
            >舊連結會立即失效，確定？</span>
            <button
              class="btn sm primary"
              type="button"
              :disabled="busy"
              @click="create()"
            >
              確定重新生成
            </button>
            <button
              class="btn sm ghost"
              type="button"
              @click="confirmRegen = false"
            >
              取消
            </button>
          </template>
          <template v-if="!confirmRevoke && !confirmRegen">
            <button
              class="btn sm danger"
              type="button"
              :disabled="busy"
              @click="confirmRevoke = true"
            >
              停用連結
            </button>
          </template>
          <template v-else-if="confirmRevoke">
            <button
              class="btn sm danger"
              type="button"
              :disabled="busy"
              @click="revoke"
            >
              確定停用
            </button>
            <button
              class="btn sm ghost"
              type="button"
              @click="confirmRevoke = false"
            >
              取消
            </button>
          </template>
          <span class="sp" />
          <span
            class="muted"
            style="font-size:12px"
          >重新生成後舊連結立即失效</span>
        </div>
        <div>
          <div
            class="muted"
            style="font-size:12px;margin-bottom:8px"
          >
            Discord / Twitter 預覽卡
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
            預覽圖會使用圖庫中第一張 SFW 圖；目前還沒有可用的圖。
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
          還沒有分享連結。產生後可以貼到 Discord、Twitter 或傳給繪師，對方會看到完整設定與色票。
        </p>
        <div class="row">
          <button
            class="btn primary"
            type="button"
            :disabled="busy"
            @click="create(false)"
          >
            產生分享連結
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
