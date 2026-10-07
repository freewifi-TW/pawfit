<script setup lang="ts">
import type { CommissionKit, Fursona, Media } from '~/types/api'

/**
 * 獸設編輯器「委託」分頁（FR-6.1、FR-6.5）：
 * 建立需求單（勾選參考圖 + 本次要求）、列表、複製連結／文字、重新生成（舊連結失效）、停用。
 */
const props = defineProps<{ fursona: Fursona, media: Media[] }>()

const { t, locale } = useI18n()
const api = useApi()
const notify = useNotify()
const { formatDate } = useLabels()

const MAX = 10
const REQUEST_FIELDS = ['composition', 'scene', 'size', 'usage', 'budget', 'deadline', 'notes'] as const
type RequestField = typeof REQUEST_FIELDS[number]

const { data: kits, refresh } = await useAsyncData(`kits-${props.fursona.id}`, () => api<CommissionKit[]>(`/commission-kits?fursona_id=${props.fursona.id}`), { default: () => [] })

const creating = ref(false)
const busy = ref(false)
const selected = ref<string[]>([])
const request = reactive<Record<RequestField, string>>({ composition: '', scene: '', size: '', usage: '', budget: '', deadline: '', notes: '' })
const confirmRegen = ref<string | null>(null)
const confirmRevoke = ref<string | null>(null)

const pickable = computed(() => props.media.filter(m => m.status === 'active'))
const selectedNsfw = computed(() => props.fursona.is_nsfw || pickable.value.some(m => selected.value.includes(m.id) && m.is_nsfw))

function toggle(id: string) {
  const i = selected.value.indexOf(id)
  if (i >= 0) selected.value.splice(i, 1)
  else if (selected.value.length < MAX) selected.value.push(id)
  else notify.err(t('commission.create.maxReached', { max: MAX }))
}

function openCreate() {
  selected.value = pickable.value.filter(m => !m.is_nsfw).slice(0, 4).map(m => m.id)
  for (const k of REQUEST_FIELDS) request[k] = ''
  creating.value = true
}

async function create() {
  if (!selected.value.length) return notify.err(t('commission.create.pickOne'))
  busy.value = true
  try {
    const body: Record<string, string> = {}
    for (const k of REQUEST_FIELDS) if (request[k].trim()) body[k] = request[k].trim()
    const kit = await api<CommissionKit>('/commission-kits', { method: 'POST', body: { fursona_id: props.fursona.id, media_ids: selected.value, request: body } })
    await refresh()
    creating.value = false
    notify.ok(t('commission.notify.created'))
    await copy(kit.url, t('commission.notify.linkCopied'))
  } catch (e) {
    const err = apiError(e)
    notify.err(t('commission.notify.failed'), Object.values(err.errors)[0] || err.message)
  } finally {
    busy.value = false
  }
}

async function regenerate(kit: CommissionKit) {
  busy.value = true
  try {
    await api(`/commission-kits/${kit.id}/regenerate`, { method: 'POST' })
    await refresh()
    confirmRegen.value = null
    notify.ok(t('commission.notify.regenerated'))
  } catch (e) {
    notify.err(t('commission.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function revoke(kit: CommissionKit) {
  busy.value = true
  try {
    await api(`/commission-kits/${kit.id}`, { method: 'DELETE' })
    await refresh()
    confirmRevoke.value = null
    notify.ok(t('commission.notify.revoked'))
  } catch (e) {
    notify.err(t('commission.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}

async function copy(text: string, okMsg = t('commission.notify.copied')) {
  try {
    await navigator.clipboard.writeText(text)
    notify.ok(okMsg)
  } catch {
    notify.err(t('commission.notify.clipboardError'))
  }
}

function briefFor(kit: CommissionKit): string {
  return kit.brief_text[locale.value] ?? Object.values(kit.brief_text)[0] ?? ''
}

const statusPill: Record<CommissionKit['status'], string> = { processing: 'warn', active: 'ok', failed: 'danger', revoked: '' }
</script>

<template>
  <div class="stack">
    <div class="card">
      <div class="hd">
        <span class="disp">{{ t('commission.panel.title') }}</span>
        <em>{{ t('commission.panel.subtitle') }}</em>
      </div>
      <div
        class="bd stack"
        style="gap:12px"
      >
        <p
          class="sub"
          style="margin:0;font-size:13px"
        >
          {{ t('commission.panel.intro') }}
        </p>
        <div class="row">
          <button
            class="btn primary"
            type="button"
            :disabled="!pickable.length"
            @click="openCreate"
          >
            {{ t('commission.panel.create') }}
          </button>
          <span
            v-if="!pickable.length"
            class="muted"
            style="font-size:12px"
          >{{ t('commission.panel.needMedia') }}</span>
        </div>
      </div>
    </div>

    <div
      v-if="kits.length"
      class="stack"
      style="gap:12px"
    >
      <div
        v-for="kit in kits"
        :key="kit.id"
        class="card kit"
        :class="{ off: kit.status === 'revoked' }"
      >
        <div
          class="bd stack"
          style="gap:10px"
        >
          <div class="row">
            <span
              class="pill"
              :class="statusPill[kit.status]"
            >{{ t(`commission.status.${kit.status}`) }}</span>
            <span
              v-if="kit.is_nsfw"
              class="pill danger"
            >NSFW</span>
            <span class="pill">{{ t('commission.panel.mediaCount', kit.media_count) }}</span>
            <span
              class="muted"
              style="font-size:12px"
            >{{ formatDate(kit.created_at, true) }}</span>
            <span class="sp" />
            <code
              class="mono"
              style="font-size:12px"
            >{{ kit.url.replace(/^https?:\/\//, '') }}</code>
          </div>
          <div
            v-if="kit.snapshot.request.composition || kit.snapshot.request.notes"
            class="sub"
            style="font-size:13px"
          >
            {{ kit.snapshot.request.composition || kit.snapshot.request.notes }}
          </div>
          <div
            v-if="kit.status !== 'revoked'"
            class="row"
          >
            <button
              class="btn sm"
              type="button"
              @click="copy(kit.url, t('commission.notify.linkCopied'))"
            >
              {{ t('commission.panel.copyLink') }}
            </button>
            <button
              class="btn sm"
              type="button"
              @click="copy(briefFor(kit))"
            >
              {{ t('commission.panel.copyBrief') }}
            </button>
            <NuxtLink
              class="btn sm ghost"
              :to="`/c/${kit.slug}`"
              target="_blank"
            >
              {{ t('commission.panel.open') }} ↗
            </NuxtLink>
            <span class="sp" />
            <template v-if="confirmRegen === kit.id">
              <span
                class="muted"
                style="font-size:12px"
              >{{ t('commission.panel.regenerateAsk') }}</span>
              <button
                class="btn sm primary"
                type="button"
                :disabled="busy"
                @click="regenerate(kit)"
              >
                {{ t('commission.panel.regenerateConfirm') }}
              </button>
              <button
                class="btn sm ghost"
                type="button"
                @click="confirmRegen = null"
              >
                {{ t('fursona.actions.cancel') }}
              </button>
            </template>
            <template v-else-if="confirmRevoke === kit.id">
              <button
                class="btn sm danger"
                type="button"
                :disabled="busy"
                @click="revoke(kit)"
              >
                {{ t('commission.panel.revokeConfirm') }}
              </button>
              <button
                class="btn sm ghost"
                type="button"
                @click="confirmRevoke = null"
              >
                {{ t('fursona.actions.cancel') }}
              </button>
            </template>
            <template v-else>
              <button
                class="btn sm ghost"
                type="button"
                :disabled="busy"
                @click="confirmRegen = kit.id"
              >
                {{ t('commission.panel.regenerate') }}
              </button>
              <button
                class="btn sm ghost"
                type="button"
                style="color:var(--danger)"
                :disabled="busy"
                @click="confirmRevoke = kit.id"
              >
                {{ t('commission.panel.revoke') }}
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>
    <div
      v-else
      class="empty"
    >
      {{ t('commission.panel.empty') }}
    </div>

    <PawDialog
      v-model:open="creating"
      :title="t('commission.create.title')"
      :description="t('commission.create.description')"
      wide
    >
      <div
        class="stack"
        style="gap:16px"
      >
        <div>
          <div
            class="row"
            style="margin-bottom:8px"
          >
            <b style="font-size:13px">{{ t('commission.create.pickTitle') }}</b>
            <span
              class="muted"
              style="font-size:12px"
            >{{ t('commission.create.pickCount', { n: selected.length, max: MAX }) }}</span>
          </div>
          <div class="pick">
            <button
              v-for="m in pickable"
              :key="m.id"
              type="button"
              class="pick-item"
              :class="{ on: selected.includes(m.id) }"
              :title="m.caption || ''"
              @click="toggle(m.id)"
            >
              <img
                :src="m.urls.thumb"
                :alt="m.caption || ''"
                loading="lazy"
              >
              <span
                v-if="selected.includes(m.id)"
                class="order"
              >{{ selected.indexOf(m.id) + 1 }}</span>
              <span
                v-if="m.is_nsfw"
                class="badge-nsfw"
              >NSFW</span>
            </button>
          </div>
          <p
            v-if="selectedNsfw"
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            {{ t('commission.create.nsfwNote') }}
          </p>
        </div>

        <div class="fields">
          <label
            v-for="k in REQUEST_FIELDS"
            :key="k"
            class="field"
            :class="{ wide: k === 'notes' || k === 'composition' || k === 'scene' }"
            style="margin:0"
          >
            <span class="lbl">{{ t(`commission.fields.${k}`) }}</span>
            <textarea
              v-if="k === 'notes' || k === 'composition' || k === 'scene'"
              v-model="request[k]"
              class="input"
              rows="2"
              :maxlength="k === 'notes' ? 2000 : 500"
              :placeholder="t(`commission.placeholders.${k}`)"
            />
            <input
              v-else
              v-model="request[k]"
              class="input"
              :maxlength="k === 'size' || k === 'usage' ? 200 : 100"
              :placeholder="t(`commission.placeholders.${k}`)"
            >
          </label>
        </div>
      </div>
      <template #footer>
        <span
          class="muted"
          style="font-size:12px"
        >{{ t('commission.create.footerNote') }}</span>
        <span class="sp" />
        <button
          class="btn ghost"
          type="button"
          @click="creating = false"
        >
          {{ t('fursona.actions.cancel') }}
        </button>
        <button
          class="btn primary"
          type="button"
          :disabled="busy || !selected.length"
          @click="create"
        >
          {{ t('commission.create.submit') }}
        </button>
      </template>
    </PawDialog>
  </div>
</template>

<style scoped>
.kit.off { opacity: .6; }
.pick { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; }
.pick-item { position: relative; aspect-ratio: 1; border-radius: var(--r-in); overflow: hidden; border: var(--border) solid var(--line); background: var(--paper-2); padding: 0; }
.pick-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pick-item.on { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.pick-item .order { position: absolute; top: 6px; left: 6px; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); color: var(--accent-ink); font-size: 12px; font-weight: 800; display: grid; place-items: center; }
.pick-item .badge-nsfw { position: absolute; right: 6px; bottom: 6px; }
.fields { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.fields .wide { grid-column: 1 / -1; }
.fields textarea { resize: vertical; }
@media (max-width: 640px) { .fields { grid-template-columns: 1fr; } }
</style>
