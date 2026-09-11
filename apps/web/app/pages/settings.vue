<script setup lang="ts">
import type { Me, NsfwPref } from '~/types/api'
import { formatBytes, formatDate } from '~/utils/labels'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: '設定' })

const api = useApi()
const notify = useNotify()
const { me, fetchMe, logout } = useAuth()

const adult = ref(!!me.value?.adult_confirmed_at)
const pref = ref<NsfwPref>(me.value?.nsfw_pref ?? 'hide')
const displayName = ref(me.value?.display_name ?? '')
const busy = ref(false)

const prefOptions: Array<{ value: NsfwPref, title: string, hint: string }> = [
  { value: 'hide', title: '完全隱藏', hint: 'NSFW 內容完全不出現，如同不存在。（預設）' },
  { value: 'blur', title: '防雷模糊', hint: '以模糊縮圖顯示，點擊後才解鎖觀看。' },
  { value: 'show', title: '直接顯示', hint: '與一般內容相同方式顯示。' }
]

async function patch(body: Record<string, unknown>, okMsg: string) {
  busy.value = true
  try {
    await api<Me>('/me', { method: 'PATCH', body })
    await fetchMe(true)
    notify.ok(okMsg)
    return true
  } catch (e) {
    const err = apiError(e)
    notify.err('儲存失敗', Object.values(err.errors)[0] || err.message)
    return false
  } finally {
    busy.value = false
  }
}

async function onAdultChange(v: boolean) {
  const ok = await patch({ adult_confirmed: v }, v ? '已記錄 18 歲聲明' : '已取消聲明，顯示方式改為完全隱藏')
  if (!ok) {
    adult.value = !v
  } else if (!v) {
    pref.value = 'hide'
  }
}

async function onPrefChange(v: NsfwPref) {
  const prev = me.value?.nsfw_pref ?? 'hide'
  const ok = await patch({ nsfw_pref: v }, '已更新顯示方式，全站立即套用')
  if (!ok) pref.value = prev
}

async function saveName() {
  if (!displayName.value.trim()) return notify.err('顯示名稱不能空白')
  await patch({ display_name: displayName.value.trim() }, '已更新顯示名稱')
}

const storagePct = computed(() => me.value ? Math.min(100, Math.round((me.value.quota.storage_used / me.value.quota.storage_limit) * 100)) : 0)
const maskedEmail = computed(() => {
  const [u, d] = (me.value?.email ?? '').split('@')
  return u && d ? `${u.slice(0, 1)}•••••@${d}` : ''
})

const section = ref<'nsfw' | 'account' | 'data'>('nsfw')
</script>

<template>
  <section
    v-if="me"
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          設定
        </h1>
        <p>帳號、內容顯示與隱私</p>
      </div>
    </div>
    <div class="set-grid">
      <nav class="side">
        <a
          href="#nsfw"
          :class="{ on: section === 'nsfw' }"
          @click="section = 'nsfw'"
        >內容顯示</a>
        <a
          href="#account"
          :class="{ on: section === 'account' }"
          @click="section = 'account'"
        >帳號</a>
        <a
          href="#data"
          :class="{ on: section === 'data' }"
          @click="section = 'data'"
        >資料與儲存</a>
        <NuxtLink to="/terms">
          服務條款
        </NuxtLink>
      </nav>

      <div class="stack">
        <div
          id="nsfw"
          class="card"
        >
          <div class="hd">
            <span class="disp">成人內容（NSFW）顯示方式</span>
            <em>{{ me.adult_confirmed_at ? `已於 ${formatDate(me.adult_confirmed_at, true)} 完成 18 歲聲明` : '尚未完成 18 歲聲明' }}</em>
          </div>
          <div
            class="bd stack"
            style="gap:16px"
          >
            <label
              class="check"
              style="font-size:14px"
            >
              <input
                v-model="adult"
                type="checkbox"
                :disabled="busy"
                @change="onAdultChange(($event.target as HTMLInputElement).checked)"
              >
              <span>我聲明我已年滿 18 歲，並自願瀏覽成人內容。<br><span
                class="muted"
                style="font-size:12px"
              >我們會記錄聲明時間。未完成聲明前，下列選項鎖定為「完全隱藏」。</span></span>
            </label>
            <div class="radio-list">
              <label
                v-for="o in prefOptions"
                :key="o.value"
                class="radio"
                :class="{ disabled: o.value !== 'hide' && !me.adult_confirmed_at }"
              >
                <input
                  v-model="pref"
                  type="radio"
                  name="np"
                  :value="o.value"
                  :disabled="busy || (o.value !== 'hide' && !me.adult_confirmed_at)"
                  @change="onPrefChange(o.value)"
                >
                <span><b>{{ o.title }}</b><small>{{ o.hint }}</small></span>
              </label>
            </div>
            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              這個設定會立即套用到全站，包含分享頁與日後的社群河道。
            </p>
          </div>
        </div>

        <div
          id="account"
          class="card"
        >
          <h2 class="disp">
            帳號
          </h2>
          <div
            class="bd stack"
            style="gap:14px"
          >
            <div class="row">
              <span class="pill ok">已綁定 Google</span>
              <span
                class="sub mono"
                style="font-size:13px"
              >{{ maskedEmail }}</span>
              <span
                v-if="me.is_admin"
                class="pill danger"
              >admin</span>
            </div>
            <div
              class="field"
              style="margin:0"
            >
              <label>Pawfit ID</label>
              <div class="prefix">
                <span>pawfit.app/u/</span>
                <input
                  :value="me.pawfit_id ?? ''"
                  readonly
                  disabled
                >
              </div>
              <div class="hint">
                更名政策確定前不開放更改。<NuxtLink
                  v-if="!me.pawfit_id"
                  class="link"
                  to="/onboarding"
                >尚未設定 →</NuxtLink>
              </div>
            </div>
            <div
              class="field"
              style="margin:0"
            >
              <label for="s-dname">顯示名稱</label>
              <div class="row">
                <input
                  id="s-dname"
                  v-model="displayName"
                  class="input"
                  style="flex:1"
                  maxlength="40"
                >
                <button
                  class="btn"
                  type="button"
                  :disabled="busy || displayName.trim() === (me.display_name ?? '')"
                  @click="saveName"
                >
                  儲存
                </button>
              </div>
            </div>
            <div class="row">
              <button
                class="btn sm ghost"
                type="button"
                style="color:var(--danger)"
                @click="logout"
              >
                登出
              </button>
            </div>
          </div>
        </div>

        <div
          id="data"
          class="card"
        >
          <h2 class="disp">
            資料與儲存
          </h2>
          <div
            class="bd stack"
            style="gap:12px"
          >
            <div class="row">
              <span style="font-weight:700">儲存空間</span><span class="sp" />
              <span class="mono sub">{{ formatBytes(me.quota.storage_used) }} / {{ formatBytes(me.quota.storage_limit) }}</span>
            </div>
            <div class="bar">
              <i :style="`width:${storagePct}%`" />
            </div>
            <div class="row">
              <span style="font-weight:700">今日上傳</span><span class="sp" />
              <span class="mono sub">{{ me.quota.uploads_today }} / {{ me.quota.daily_limit }} 張</span>
            </div>
            <p
              class="muted"
              style="font-size:12px;margin:6px 0 0"
            >
              匯出全部資料與刪除帳號功能將於後續版本提供；如需刪除帳號請透過頁尾聯絡方式來信。
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
