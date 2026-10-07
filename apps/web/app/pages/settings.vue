<script setup lang="ts">
import type { Fursona, Me, NsfwPref } from '~/types/api'
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

const section = ref<'nsfw' | 'language' | 'embed' | 'account' | 'data'>('nsfw')

/* ---- 開放與嵌入（FR-7）：總開關、各獸設覆寫、嵌入程式碼 ---- */
const embedOn = ref(!!me.value?.allow_embed_api)
const { data: fursonas, refresh: refreshFursonas } = await useAsyncData('settings-fursonas', () => api<Fursona[]>('/fursonas'), { default: () => [] })
const openCode = ref<string | null>(null)

type EmbedChoice = 'inherit' | 'on' | 'off'
function embedChoice(f: Fursona): EmbedChoice {
  return f.allow_embed_api === null || f.allow_embed_api === undefined ? 'inherit' : f.allow_embed_api ? 'on' : 'off'
}
/** 這隻獸設實際能否被嵌入：開關 + 有分享連結 + 非私人 + 非 NSFW（NSFW 對訪客永遠隱藏） */
function embeddable(f: Fursona): boolean {
  return !!f.embed_enabled && !!f.share_link && f.visibility !== 'private' && !f.is_nsfw && !f.removed_at
}
function embedBlockReason(f: Fursona): string | null {
  if (!f.embed_enabled) return null
  if (f.removed_at) return t('settings.embed.reasons.removed')
  if (f.visibility === 'private') return t('settings.embed.reasons.private')
  if (f.is_nsfw) return t('settings.embed.reasons.nsfw')
  if (!f.share_link) return t('settings.embed.reasons.noLink')
  return null
}

async function onEmbedToggle(v: boolean) {
  const ok = await patch({ allow_embed_api: v }, v ? t('settings.embed.enabled') : t('settings.embed.disabled'))
  if (!ok) embedOn.value = !v
  else await refreshFursonas()
}

async function onFursonaEmbedChange(f: Fursona, choice: EmbedChoice) {
  busy.value = true
  try {
    await api<Fursona>(`/fursonas/${f.id}`, { method: 'PATCH', body: { allow_embed_api: choice === 'inherit' ? null : choice === 'on' } })
    await refreshFursonas()
    notify.ok(t('settings.embed.fursonaUpdated', { name: f.name }))
  } catch (e) {
    notify.err(t('common.notify.saveFailed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}
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
          href="#embed"
          :class="{ on: section === 'embed' }"
          @click="section = 'embed'"
        >{{ t('settings.nav.embed') }}</a>
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
          id="embed"
          class="card"
        >
          <div class="hd">
            <span class="disp">{{ t('settings.embed.heading') }}</span>
            <em>{{ embedOn ? t('settings.embed.stateOn') : t('settings.embed.stateOff') }}</em>
          </div>
          <div
            class="bd stack"
            style="gap:16px"
          >
            <label class="switch">
              <input
                v-model="embedOn"
                type="checkbox"
                :disabled="busy"
                @change="onEmbedToggle(($event.target as HTMLInputElement).checked)"
              > {{ t('settings.embed.master') }}
            </label>
            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('settings.embed.masterNote') }}
            </p>

            <div
              v-if="fursonas.length"
              class="embed-list"
            >
              <div
                v-for="f in fursonas"
                :key="f.id"
                class="embed-item"
              >
                <div class="row">
                  <BlobAvatar
                    :src="f.avatar_url"
                    :size="36"
                    :border="2"
                    :alt="f.name"
                  />
                  <b style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ f.name }}</b>
                  <span
                    v-if="embeddable(f)"
                    class="pill ok"
                  >{{ t('settings.embed.status.open') }}</span>
                  <span
                    v-else-if="embedBlockReason(f)"
                    class="pill warn"
                    :title="embedBlockReason(f) ?? ''"
                  >{{ embedBlockReason(f) }}</span>
                  <span
                    v-else
                    class="pill"
                  >{{ t('settings.embed.status.closed') }}</span>
                  <span class="sp" />
                  <select
                    class="input"
                    style="width:auto;padding:6px 10px;font-size:13px"
                    :value="embedChoice(f)"
                    :disabled="busy"
                    :aria-label="t('settings.embed.overrideLabel', { name: f.name })"
                    @change="onFursonaEmbedChange(f, ($event.target as HTMLSelectElement).value as EmbedChoice)"
                  >
                    <option value="inherit">
                      {{ t('settings.embed.override.inherit') }}
                    </option>
                    <option value="on">
                      {{ t('settings.embed.override.on') }}
                    </option>
                    <option value="off">
                      {{ t('settings.embed.override.off') }}
                    </option>
                  </select>
                  <button
                    v-if="embeddable(f)"
                    class="btn sm"
                    type="button"
                    :aria-expanded="openCode === f.id"
                    @click="openCode = openCode === f.id ? null : f.id"
                  >
                    {{ openCode === f.id ? t('settings.embed.hideCode') : t('settings.embed.getCode') }}
                  </button>
                  <NuxtLink
                    v-else-if="f.embed_enabled && !f.share_link && f.visibility !== 'private' && !f.is_nsfw"
                    class="btn sm ghost"
                    :to="`/fursona/${f.id}#share`"
                  >
                    {{ t('settings.embed.createLink') }}
                  </NuxtLink>
                </div>
                <EmbedCodePanel
                  v-if="openCode === f.id && f.share_link"
                  :slug="f.share_link.slug"
                  :name="f.name"
                />
              </div>
            </div>
            <p
              v-else
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('settings.embed.noFursonas') }}
            </p>

            <p
              class="muted"
              style="font-size:12px;margin:0"
            >
              {{ t('settings.embed.apiDocs') }} <code class="mono">GET /api/v1/public/users/{{ me.pawfit_id ?? 'your_id' }}</code> ·
              <NuxtLink
                class="link"
                to="/terms#api"
              >{{ t('settings.embed.termsLink') }}</NuxtLink>
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

<style scoped>
.embed-list { display: grid; gap: 10px; }
.embed-item { display: grid; gap: 12px; padding: 12px 14px; border: var(--border) solid var(--line); border-radius: var(--r-in); background: var(--paper-2); }
</style>
