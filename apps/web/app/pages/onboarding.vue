<script setup lang="ts">
import type { Me } from '~/types/api'

definePageMeta({ middleware: 'auth' })

const { t } = useI18n()
useSeoMeta({ title: () => t('auth.onboarding.title') })

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
    case 'checking': return t('auth.onboarding.idHint.checking')
    case 'ok': return t('auth.onboarding.idHint.ok')
    case 'taken': return t('auth.onboarding.idHint.taken')
    case 'invalid': return pawfitId.value ? t('auth.onboarding.idHint.invalid') : ''
    default: return t('auth.onboarding.idHint.default')
  }
})

async function submit() {
  errors.value = {}
  if (!idValid.value) errors.value.pawfit_id = t('auth.onboarding.errors.idFormat')
  if (!displayName.value.trim()) errors.value.display_name = t('auth.onboarding.errors.displayName')
  if (!tos.value) errors.value.tos = t('auth.onboarding.errors.tos')
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
    notify.ok(t('auth.onboarding.done'))
    await navigateTo('/dashboard?new=1')
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    if (!Object.keys(err.errors).length) notify.err(t('common.notify.saveFailed'), err.message)
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
        <i>✓</i>{{ t('auth.onboarding.steps.google') }}<span>—</span><i class="on">2</i>{{ t('auth.onboarding.steps.id') }}<span>—</span><i>3</i>{{ t('auth.onboarding.steps.fursona') }}
      </div>
      <h1 class="disp">
        {{ t('auth.onboarding.heading') }}
      </h1>
      <p
        class="sub"
        style="margin:0 0 22px"
      >
        {{ t('auth.onboarding.lead') }}
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
        <label for="dname">{{ t('auth.onboarding.displayName.label') }}</label>
        <input
          id="dname"
          v-model="displayName"
          class="input"
          :class="{ 'is-invalid': errors.display_name }"
          maxlength="40"
          :placeholder="t('auth.onboarding.displayName.placeholder')"
        >
        <div class="hint">
          {{ errors.display_name || t('auth.onboarding.displayName.hint') }}
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
        <i18n-t
          keypath="auth.onboarding.agree"
          tag="span"
        >
          <template #terms>
            <NuxtLink
              class="link"
              to="/terms"
              target="_blank"
            >{{ t('common.legal.terms') }}</NuxtLink>
          </template>
          <template #guidelines>
            <NuxtLink
              class="link"
              to="/guidelines"
              target="_blank"
            >{{ t('common.legal.guidelines') }}</NuxtLink>
          </template>
        </i18n-t>
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
        <span>{{ t('auth.onboarding.adult') }}<span class="muted">{{ t('auth.onboarding.adultNote') }}</span></span>
      </label>

      <div class="row">
        <span class="sp" />
        <button
          class="btn primary lg"
          type="submit"
          :disabled="busy || availability === 'taken' || availability === 'checking'"
        >
          {{ t('auth.onboarding.submit') }}
        </button>
      </div>
    </form>
  </section>
</template>
