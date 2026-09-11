<script setup lang="ts">
import type { Me } from '~/types/api'

definePageMeta({ middleware: 'guest' })

const { t } = useI18n()
useSeoMeta({ title: () => t('auth.login.title') })

const config = useRuntimeConfig()
const route = useRoute()
const api = useApi()
const notify = useNotify()
const { fetchMe } = useAuth()

const tos = ref(true)
const next = computed(() => (typeof route.query.next === 'string' && route.query.next.startsWith('/') ? route.query.next : ''))

const errorText = computed(() => {
  switch (route.query.error) {
    case 'banned': return t('auth.login.errorBanned')
    case 'oauth': return t('auth.login.errorOauth')
    default: return ''
  }
})

function google() {
  if (!tos.value) {
    notify.err(t('auth.login.tosRequired'))
    return
  }
  // OAuth 流程由 Laravel 處理；完成後依 onboarding 狀態轉址
  window.location.href = `${config.public.apiBase}/auth/google/redirect`
}

// 本機開發：沒有 Google 憑證時用 email 直接登入
const devEmail = ref('demo@pawfit.local')
const devBusy = ref(false)
async function devLogin() {
  if (!tos.value) {
    notify.err(t('auth.login.tosRequired'))
    return
  }
  devBusy.value = true
  try {
    await api<{ user: Me }>('/auth/dev-login', { method: 'POST', body: { email: devEmail.value } })
    const me = await fetchMe(true)
    await navigateTo(next.value || (me?.is_onboarded ? '/dashboard' : '/onboarding'))
  } catch (e) {
    notify.err(t('auth.login.failed'), apiError(e).message)
  } finally {
    devBusy.value = false
  }
}
</script>

<template>
  <section class="wrap narrow auth">
    <div class="card">
      <h1 class="disp">
        {{ t('auth.login.welcome') }}
      </h1>
      <p
        class="sub"
        style="margin:0 0 22px"
      >
        {{ t('auth.login.lead') }}
      </p>

      <p
        v-if="errorText"
        class="visitor-note"
        style="margin:0 0 16px;border-color:var(--danger);background:var(--danger-soft)"
      >
        {{ errorText }}
      </p>

      <button
        class="btn gbtn"
        type="button"
        @click="google"
      >
        <svg
          viewBox="0 0 48 48"
          aria-hidden="true"
        ><path
          fill="#EA4335"
          d="M24 9.5c3.5 0 6.6 1.2 9 3.5l6.7-6.7C35.6 2.6 30.2 0 24 0 14.6 0 6.5 5.4 2.6 13.3l7.8 6C12.3 13.6 17.7 9.5 24 9.5z"
        /><path
          fill="#4285F4"
          d="M46.5 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.7c-.6 3-2.3 5.5-4.8 7.2l7.5 5.8c4.4-4.1 7.1-10.1 7.1-17.5z"
        /><path
          fill="#FBBC05"
          d="M10.4 28.7A14.5 14.5 0 0 1 9.5 24c0-1.6.3-3.2.8-4.7l-7.8-6A24 24 0 0 0 0 24c0 3.9.9 7.5 2.6 10.7l7.8-6z"
        /><path
          fill="#34A853"
          d="M24 48c6.2 0 11.6-2 15.4-5.6l-7.5-5.8c-2 1.4-4.7 2.3-7.9 2.3-6.3 0-11.7-4.1-13.6-9.8l-7.8 6C6.5 42.6 14.6 48 24 48z"
        /></svg>
        {{ t('auth.login.google') }}
      </button>

      <div style="height:16px" />
      <label class="check">
        <input
          v-model="tos"
          type="checkbox"
        >
        <i18n-t
          keypath="auth.login.agree"
          tag="span"
        >
          <template #terms>
            <NuxtLink
              class="link"
              to="/terms"
            >{{ t('common.legal.terms') }}</NuxtLink>
          </template>
          <template #guidelines>
            <NuxtLink
              class="link"
              to="/guidelines"
            >{{ t('common.legal.guidelines') }}</NuxtLink>
          </template>
        </i18n-t>
      </label>
      <p
        class="muted"
        style="font-size:12px;margin:18px 0 0"
      >
        {{ t('auth.login.appleNote') }}
      </p>

      <div
        v-if="config.public.devLogin"
        style="margin-top:26px;padding-top:18px;border-top:2px dashed var(--line)"
      >
        <div class="row">
          <span class="pill warn">{{ t('auth.login.dev.badge') }}</span><span
            class="muted"
            style="font-size:12px"
          >{{ t('auth.login.dev.hint') }}</span>
        </div>
        <form
          class="row"
          style="margin-top:10px"
          @submit.prevent="devLogin"
        >
          <input
            v-model="devEmail"
            class="input"
            type="email"
            style="flex:1;min-width:200px"
            placeholder="you@example.com"
            required
          >
          <button
            class="btn"
            type="submit"
            :disabled="devBusy"
          >
            {{ t('auth.login.dev.submit') }}
          </button>
        </form>
        <i18n-t
          keypath="auth.login.dev.note"
          tag="p"
          class="muted"
          style="font-size:12px;margin:8px 0 0"
        >
          <template #demo>
            <span class="mono">demo@pawfit.local</span>
          </template>
          <template #admin>
            <span class="mono">admin@pawfit.local</span>
          </template>
        </i18n-t>
      </div>
    </div>
  </section>
</template>
