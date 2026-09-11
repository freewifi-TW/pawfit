<script setup lang="ts">
import type { Me } from '~/types/api'

definePageMeta({ middleware: 'auth' })
useSeoMeta({ title: '設定 Pawfit ID' })

const api = useApi()
const notify = useNotify()
const { me, fetchMe } = useAuth()

if (me.value?.is_onboarded) {
  await navigateTo('/dashboard', { replace: true })
}

const pawfitId = ref(me.value?.pawfit_id ?? '')
const displayName = ref(me.value?.display_name ?? '')
const tos = ref(false)
const adult = ref(false)
const busy = ref(false)
const errors = ref<Record<string, string>>({})

const idValid = computed(() => /^[A-Za-z0-9_]{3,20}$/.test(pawfitId.value))
const availability = ref<'unknown' | 'checking' | 'ok' | 'taken' | 'invalid'>('unknown')
let timer: ReturnType<typeof setTimeout> | null = null

watch(pawfitId, (v) => {
  errors.value.pawfit_id = ''
  if (timer) clearTimeout(timer)
  if (!v) {
    availability.value = 'unknown'
    return
  }
  if (!idValid.value) {
    availability.value = 'invalid'
    return
  }
  availability.value = 'checking'
  timer = setTimeout(async () => {
    try {
      const r = await api<{ available: boolean, valid: boolean }>('/me/pawfit-id-available', { query: { pawfit_id: v } })
      availability.value = !r.valid ? 'invalid' : r.available ? 'ok' : 'taken'
    } catch {
      availability.value = 'unknown'
    }
  }, 350)
})

const hint = computed(() => {
  switch (availability.value) {
    case 'checking': return '檢查中…'
    case 'ok': return '✓ 可以使用'
    case 'taken': return '這個 Pawfit ID 已經有人用了'
    case 'invalid': return pawfitId.value ? '3 到 20 字元，英數與底線；部分保留字不可用' : ''
    default: return '3 到 20 字元，英數與底線。之後更名政策確定前不可更改。'
  }
})

async function submit() {
  errors.value = {}
  if (!idValid.value) errors.value.pawfit_id = 'Pawfit ID 格式不正確。'
  if (!displayName.value.trim()) errors.value.display_name = '請填顯示名稱。'
  if (!tos.value) errors.value.tos = '需同意服務條款與社群守則才能繼續。'
  if (Object.keys(errors.value).length) return

  busy.value = true
  try {
    await api<Me>('/me', {
      method: 'PATCH',
      body: {
        pawfit_id: pawfitId.value,
        display_name: displayName.value.trim(),
        tos_accepted: true,
        ...(adult.value ? { adult_confirmed: true } : {})
      }
    })
    await fetchMe(true)
    notify.ok('設定完成！來建立第一隻獸設吧')
    await navigateTo('/dashboard?new=1')
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    if (!Object.keys(err.errors).length) notify.err('儲存失敗', err.message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="wrap narrow auth">
    <form
      class="card"
      @submit.prevent="submit"
    >
      <div class="steps">
        <i>✓</i>Google 登入<span>—</span><i class="on">2</i>設定 Pawfit ID<span>—</span><i>3</i>建立第一隻獸設
      </div>
      <h1 class="disp">
        替自己取個 Pawfit ID
      </h1>
      <p
        class="sub"
        style="margin:0 0 22px"
      >
        這會成為你的個人主頁網址，也是日後朋友加你好友的方式。
      </p>

      <div class="field">
        <label for="pid">Pawfit ID</label>
        <div
          class="prefix"
          :style="errors.pawfit_id || availability === 'taken' ? 'border-color:var(--danger)' : ''"
        >
          <span>pawfit.app/u/</span>
          <input
            id="pid"
            v-model="pawfitId"
            maxlength="20"
            autocomplete="off"
            spellcheck="false"
            placeholder="firefox_ash"
          >
        </div>
        <div
          class="hint"
          :style="availability === 'ok' ? 'color:var(--ok)' : (availability === 'taken' || availability === 'invalid') ? 'color:var(--danger)' : ''"
        >
          {{ errors.pawfit_id || hint }}
        </div>
      </div>

      <div class="field">
        <label for="dname">顯示名稱</label>
        <input
          id="dname"
          v-model="displayName"
          class="input"
          :class="{ 'is-invalid': errors.display_name }"
          maxlength="40"
          placeholder="Ash 灰灰"
        >
        <div class="hint">
          {{ errors.display_name || '顯示在主頁與分享頁，隨時可改。' }}
        </div>
      </div>

      <label
        class="check"
        style="margin-bottom:10px"
      >
        <input
          v-model="tos"
          type="checkbox"
        >
        <span>我已年滿 13 歲，並同意 <NuxtLink
          class="link"
          to="/terms"
          target="_blank"
        >服務條款</NuxtLink> 與 <NuxtLink
          class="link"
          to="/guidelines"
          target="_blank"
        >社群守則</NuxtLink>。</span>
      </label>
      <div
        v-if="errors.tos"
        class="err"
        style="font-size:12px;color:var(--danger);font-weight:700;margin:-6px 0 10px"
      >
        {{ errors.tos }}
      </div>
      <label
        class="check"
        style="margin-bottom:22px"
      >
        <input
          v-model="adult"
          type="checkbox"
        >
        <span>我已年滿 18 歲，想要瀏覽成人內容。<span class="muted">（可稍後在設定中完成；聲明時間會被記錄）</span></span>
      </label>

      <div class="row">
        <span class="sp" />
        <button
          class="btn primary lg"
          type="submit"
          :disabled="busy || availability === 'taken' || availability === 'checking'"
        >
          完成，建立第一隻獸設
        </button>
      </div>
    </form>
  </section>
</template>
