<script setup lang="ts">
import type { Me, NsfwPref } from '~/types/api'
import { formatBytes } from '~/utils/labels'

definePageMeta({ middleware: 'auth' })

const { t } = useI18n()
useSeoMeta({ title: () => t('settings.title') })

const api = useApi()
const notify = useNotify()
const { me, fetchMe, logout } = useAuth()
const { formatDate } = useLabels()
const { locale, available, switchLocale } = useLocale()

const adult = ref(!!me.value?.adult_confirmed_at)
const pref = ref<NsfwPref>(me.value?.nsfw_pref ?? 'hide')
const displayName = ref(me.value?.display_name ?? '')
const busy = ref(false)

const prefOptions = computed<Array<{ value: NsfwPref, title: string, hint: string }>>(() =>
  (['hide', 'blur', 'show'] as NsfwPref[]).map(value => ({
    value,
    title: t(`settings.nsfw.options.${value}.title`),
    hint: t(`settings.nsfw.options.${value}.hint`)
  }))
)

async function patch(body: Record<string, unknown>, okMsg: string) {
  busy.value = true
  try {
    await api<Me>('/me', { method: 'PATCH', body })
    await fetchMe(true)
    notify.ok(okMsg)
    return true
  } catch (e) {
    const err = apiError(e)
    notify.err(t('common.notify.saveFailed'), Object.values(err.errors)[0] || err.message)
    return false
  } finally {
    busy.value = false
  }
}

async function onAdultChange(v: boolean) {
  const ok = await patch({ adult_confirmed: v }, v ? t('settings.nsfw.confirmed') : t('settings.nsfw.unconfirmed'))
  if (!ok) {
    adult.value = !v
  } else if (!v) {
    pref.value = 'hide'
  }
}

async function onPrefChange(v: NsfwPref) {
  const prev = me.value?.nsfw_pref ?? 'hide'
  const ok = await patch({ nsfw_pref: v }, t('settings.nsfw.prefUpdated'))
  if (!ok) pref.value = prev
}

async function saveName() {
  if (!displayName.value.trim()) return notify.err(t('settings.account.nameEmpty'))
  await patch({ display_name: displayName.value.trim() }, t('settings.account.nameUpdated'))
}

const storagePct = computed(() => me.value ? Math.min(100, Math.round((me.value.quota.storage_used / me.value.quota.storage_limit) * 100)) : 0)
const maskedEmail = computed(() => {
  const [u, d] = (me.value?.email ?? '').split('@')
  return u && d ? `${u.slice(0, 1)}•••••@${d}` : ''
})

const section = ref<'nsfw' | 'language' | 'account' | 'data'>('nsfw')
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
          {{ t('settings.title') }}
        </h1>
        <p>{{ t('settings.subtitle') }}</p>
      </div>
    </div>
    <div class="set-grid">
      <nav class="side">
        <a
          href="#nsfw"
          :class="{ on: section === 'nsfw' }"
          @click="section = 'nsfw'"
        >{{ t('settings.nav.nsfw') }}</a>
        <a
          href="#language"
          :class="{ on: section === 'language' }"
          @click="section = 'language'"
        >{{ t('settings.nav.language') }}</a>
        <a
          href="#account"
          :class="{ on: section === 'account' }"
          @click="section = 'account'"
        >{{ t('settings.nav.account') }}</a>
        <a
          href="#data"
          :class="{ on: section === 'data' }"
          @click="section = 'data'"
        >{{ t('settings.nav.data') }}</a>
        <NuxtLink to="/terms">
          {{ t('common.legal.terms') }}
        </NuxtLink>
      </nav>

      <div class="stack">
        <div
          id="nsfw"
          class="card"
        >
          <div class="hd">
            <span class="disp">{{ t('settings.nsfw.heading') }}</span>
            <em>{{ me.adult_confirmed_at ? t('settings.nsfw.confirmedAt', { date: formatDate(me.adult_confirmed_at, true) }) : t('settings.nsfw.notConfirmed') }}</em>
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
              <span>{{ t('settings.nsfw.declare') }}<br><span
                class="muted"
                style="font-size:12px"
              >{{ t('settings.nsfw.declareNote') }}</span></span>
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
              {{ t('settings.nsfw.applyNote') }}
            </p>
          </div>
        </div>

        <div
          id="language"
          class="card"
        >
          <h2 class="disp">
            {{ t('settings.language.heading') }}
          </h2>
          <div
            class="bd stack"
            style="gap:12px"
          >
            <div class="row">
              <button
                v-for="l in available"
                :key="l.code"
                class="btn sm"
                :class="{ primary: l.code === locale }"
                type="button"
                :aria-pressed="l.code === locale"
                @click="switchLocale(l.code)"
              >
                {{ l.name }}
              </button>
            </div>
            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('settings.language.hint') }}
            </p>
          </div>
        </div>

        <div
          id="account"
          class="card"
        >
          <h2 class="disp">
            {{ t('settings.account.heading') }}
          </h2>
          <div
            class="bd stack"
            style="gap:14px"
          >
            <div class="row">
              <span class="pill ok">{{ t('settings.account.googleLinked') }}</span>
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
                {{ t('settings.account.idNote') }}<NuxtLink
                  v-if="!me.pawfit_id"
                  class="link"
                  to="/onboarding"
                >{{ t('settings.account.idUnset') }}</NuxtLink>
              </div>
            </div>
            <div
              class="field"
              style="margin:0"
            >
              <label for="s-dname">{{ t('settings.account.displayName') }}</label>
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
                  {{ t('settings.account.save') }}
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
                {{ t('common.nav.logout') }}
              </button>
            </div>
          </div>
        </div>

        <div
          id="data"
          class="card"
        >
          <h2 class="disp">
            {{ t('settings.data.heading') }}
          </h2>
          <div
            class="bd stack"
            style="gap:12px"
          >
            <div class="row">
              <span style="font-weight:700">{{ t('settings.data.storage') }}</span><span class="sp" />
              <span class="mono sub">{{ formatBytes(me.quota.storage_used) }} / {{ formatBytes(me.quota.storage_limit) }}</span>
            </div>
            <div class="bar">
              <i :style="`width:${storagePct}%`" />
            </div>
            <div class="row">
              <span style="font-weight:700">{{ t('settings.data.uploadsToday') }}</span><span class="sp" />
              <span class="mono sub">{{ t('settings.data.uploadsCount', { used: me.quota.uploads_today, limit: me.quota.daily_limit }) }}</span>
            </div>
            <p
              class="muted"
              style="font-size:12px;margin:6px 0 0"
            >
              {{ t('settings.data.note') }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
