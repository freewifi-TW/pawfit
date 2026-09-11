<script setup lang="ts">
import type { AdminStats, Paginated, Report, ReportStatus } from '~/types/api'
import { REASON_PILL } from '~/utils/labels'

definePageMeta({ middleware: ['auth', 'admin'] })

const { t } = useI18n()
const { adminActionLabel, reasonLabel, formatDate } = useLabels()

useSeoMeta({ title: () => t('admin.title'), robots: 'noindex' })

const api = useApi()
const notify = useNotify()

const status = ref<ReportStatus | 'all'>('open')
const { data: stats, refresh: refreshStats } = await useAsyncData('admin-stats', () => api<AdminStats>('/admin/stats'))
const { data: reports, refresh: refreshReports } = await useAsyncData(
  'admin-reports',
  () => api<Paginated<Report>>('/admin/reports', { query: { status: status.value } }),
  { watch: [status] }
)

const busyId = ref<string | null>(null)
const preview = ref<Report | null>(null)

async function act(report: Report, action: string, targetId: string, note?: string) {
  busyId.value = report.id
  try {
    await api('/admin/actions', { method: 'POST', body: { action, target_id: targetId, report_id: report.id, note: note ?? null } })
    notify.ok(t('admin.notify.done', { action: adminActionLabel(action) }))
    await Promise.all([refreshReports(), refreshStats()])
  } catch (e) {
    notify.err(t('admin.notify.failed'), apiError(e).message)
  } finally {
    busyId.value = null
  }
}

function targetLabel(r: Report): string {
  const tg = r.target
  if (!tg) return t('admin.target.deleted')
  switch (r.target_type) {
    case 'media':
      return tg.fursona_name
        ? t('admin.target.mediaWithFursona', { label: tg.label, fursona: tg.fursona_name })
        : t('admin.target.media', { label: tg.label })
    case 'fursona': return t('admin.target.fursona', { label: tg.label })
    default: return t('admin.target.user', { label: tg.label })
  }
}

/** 找出「這筆檢舉的內容擁有者」以便停權。 */
function ownerIdOf(_r: Report): string | null {
  return null
}
void ownerIdOf
</script>

<template>
  <section
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          {{ t('admin.title') }}
        </h1>
        <p>{{ t('admin.subtitle') }}</p>
      </div>
      <span class="sp" /><span class="pill danger">admin</span>
    </div>

    <div
      v-if="stats"
      class="kpi"
    >
      <div class="card">
        <b class="disp">{{ stats.open }}</b><span>{{ t('admin.stats.open') }}</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.open_illegal }}</b><span>{{ t('admin.stats.openIllegal') }}</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.resolved_this_week }}</b><span>{{ t('admin.stats.resolvedThisWeek') }}</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.banned_users }}</b><span>{{ t('admin.stats.bannedUsers') }}</span>
      </div>
    </div>

    <div class="card">
      <div class="hd">
        <span class="disp">{{ t('admin.queue.title') }}</span>
        <div class="seg sm">
          <button
            type="button"
            :class="{ on: status === 'open' }"
            @click="status = 'open'"
          >
            {{ t('admin.status.open') }}
          </button>
          <button
            type="button"
            :class="{ on: status === 'resolved' }"
            @click="status = 'resolved'"
          >
            {{ t('admin.status.resolved') }}
          </button>
          <button
            type="button"
            :class="{ on: status === 'dismissed' }"
            @click="status = 'dismissed'"
          >
            {{ t('admin.status.dismissed') }}
          </button>
          <button
            type="button"
            :class="{ on: status === 'all' }"
            @click="status = 'all'"
          >
            {{ t('admin.status.all') }}
          </button>
        </div>
        <em>{{ t('admin.queue.hint') }}</em>
      </div>
      <div class="bd tblwrap">
        <table
          v-if="reports?.data.length"
          class="tbl"
        >
          <thead>
            <tr><th>{{ t('admin.table.target') }}</th><th>{{ t('admin.table.reason') }}</th><th>{{ t('admin.table.reporter') }}</th><th>{{ t('admin.table.time') }}</th><th>{{ t('admin.table.status') }}</th><th /></tr>
          </thead>
          <tbody>
            <tr
              v-for="r in reports.data"
              :key="r.id"
              :style="r.status !== 'open' ? 'opacity:.65' : ''"
            >
              <td>
                <img
                  v-if="r.target_type === 'media' && r.target?.thumb_url && r.target.status === 'active'"
                  class="thumbcell"
                  :src="r.target.thumb_url"
                  alt=""
                  style="margin-right:6px;cursor:zoom-in"
                  @click="preview = r"
                >
                {{ targetLabel(r) }}
                <div
                  v-if="r.target?.owner_pawfit_id"
                  class="muted"
                  style="font-size:11px"
                >
                  {{ t('admin.target.owner') }} <NuxtLink
                    :to="`/u/${r.target.owner_pawfit_id}`"
                    class="link"
                  >@{{ r.target.owner_pawfit_id }}</NuxtLink>
                  <template v-if="r.target_type === 'media'">
                    · {{ r.target.is_nsfw ? 'NSFW' : 'SFW' }} · {{ r.target.status }}
                  </template>
                  <template v-if="r.target_type === 'fursona' && r.target.removed_at">
                    · {{ t('admin.target.removed') }}
                  </template>
                  <template v-if="r.target_type === 'profile' && r.target.is_banned">
                    · {{ t('admin.target.banned') }}
                  </template>
                </div>
                <div
                  v-if="r.detail"
                  class="sub"
                  style="font-size:12px;max-width:36ch"
                >
                  {{ t('admin.target.detailQuote', { detail: r.detail }) }}
                </div>
              </td>
              <td>
                <span
                  class="pill"
                  :class="REASON_PILL[r.reason_code]"
                >{{ reasonLabel(r.reason_code) }}</span>
              </td>
              <td>@{{ r.reporter?.pawfit_id ?? '?' }}</td>
              <td class="mono">
                {{ formatDate(r.created_at, true) }}
              </td>
              <td>
                <span
                  class="pill"
                  :class="r.status === 'open' ? 'warn' : r.status === 'resolved' ? 'ok' : ''"
                >{{ t(`admin.status.${r.status}`) }}</span>
              </td>
              <td>
                <div
                  v-if="r.status === 'open' && r.target"
                  class="row"
                  style="gap:6px;flex-wrap:nowrap"
                >
                  <template v-if="r.target_type === 'media'">
                    <button
                      v-if="r.target.status === 'active'"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="preview = r"
                    >
                      {{ t('admin.actions.preview') }}
                    </button>
                    <button
                      v-if="!r.target.is_nsfw"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'mark_nsfw', r.target_id)"
                    >
                      {{ t('admin.actions.markNsfw') }}
                    </button>
                    <button
                      v-if="r.target.status === 'active'"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'remove_media', r.target_id)"
                    >
                      {{ t('admin.actions.remove') }}
                    </button>
                    <button
                      v-if="r.reason_code === 'illegal'"
                      class="btn sm danger"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'purge_media', r.target_id, t('admin.notes.purge'))"
                    >
                      {{ t('admin.actions.purge') }}
                    </button>
                  </template>
                  <template v-else-if="r.target_type === 'fursona'">
                    <NuxtLink
                      v-if="r.target.fursona_id"
                      class="btn sm"
                      :to="`/fursona/${r.target.fursona_id}`"
                      target="_blank"
                    >
                      {{ t('admin.actions.view') }}
                    </NuxtLink>
                    <button
                      v-if="!r.target.removed_at"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'remove_fursona', r.target_id)"
                    >
                      {{ t('admin.actions.remove') }}
                    </button>
                  </template>
                  <template v-else>
                    <button
                      v-if="!r.target.is_banned"
                      class="btn sm danger"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'ban_user', r.target_id)"
                    >
                      {{ t('admin.actions.ban') }}
                    </button>
                  </template>
                  <button
                    class="btn sm ghost"
                    type="button"
                    :disabled="busyId === r.id"
                    @click="act(r, 'dismiss_report', r.id)"
                  >
                    {{ t('admin.actions.dismiss') }}
                  </button>
                </div>
                <div
                  v-else-if="r.status !== 'open'"
                  class="muted"
                  style="font-size:12px"
                >
                  {{ r.resolved_by ? t('admin.resolvedBy', { id: r.resolved_by.pawfit_id }) : '' }} {{ formatDate(r.resolved_at, true) }}
                </div>
                <div
                  v-else
                  class="row"
                  style="gap:6px"
                >
                  <button
                    class="btn sm ghost"
                    type="button"
                    @click="act(r, 'resolve_report', r.id, t('admin.notes.targetGone'))"
                  >
                    {{ t('admin.actions.resolve') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        <div
          v-else
          class="empty"
        >
          {{ t('admin.queue.empty') }}
        </div>
      </div>
    </div>

    <div
      v-if="stats"
      class="card"
      style="margin-top:22px"
    >
      <h2 class="disp">
        {{ t('admin.log.title') }}
      </h2>
      <div
        class="bd"
        style="font-size:13px;display:grid;gap:8px"
      >
        <div
          v-for="a in stats.recent_actions"
          :key="a.id"
          class="row"
        >
          <span class="mono muted">{{ formatDate(a.created_at, true) }}</span>
          <span>{{ a.admin ?? 'admin' }} · {{ adminActionLabel(a.action) }} <span class="mono">{{ a.target_type }}/{{ a.target_id.slice(0, 8) }}</span><template v-if="a.note">{{ t('admin.log.note', { note: a.note }) }}</template></span>
        </div>
        <div
          v-if="!stats.recent_actions.length"
          class="muted"
        >
          {{ t('admin.log.empty') }}
        </div>
      </div>
    </div>

    <PawDialog
      :open="!!preview"
      :title="t('admin.preview.title')"
      :description="preview ? targetLabel(preview) : ''"
      wide
      @update:open="(v) => !v && (preview = null)"
    >
      <img
        v-if="preview?.target?.thumb_url"
        :src="preview.target.thumb_url.replace('v=thumb', 'v=display')"
        alt=""
        style="max-width:100%;border-radius:12px;display:block;margin:0 auto"
      >
      <template #footer>
        <button
          class="btn"
          type="button"
          @click="preview = null"
        >
          {{ t('admin.preview.close') }}
        </button>
      </template>
    </PawDialog>
  </section>
</template>
