<script setup lang="ts">
import type { AdminStats, Paginated, Report, ReportStatus } from '~/types/api'
import { ADMIN_ACTION_LABEL, formatDate, REASON_LABEL, REASON_PILL } from '~/utils/labels'

definePageMeta({ middleware: ['auth', 'admin'] })
useSeoMeta({ title: '管理後台', robots: 'noindex' })

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
    notify.ok(`已${ADMIN_ACTION_LABEL[action] ?? action}`)
    await Promise.all([refreshReports(), refreshStats()])
  } catch (e) {
    notify.err('操作失敗', apiError(e).message)
  } finally {
    busyId.value = null
  }
}

function targetLabel(r: Report): string {
  const t = r.target
  if (!t) return '（目標已刪除）'
  switch (r.target_type) {
    case 'media': return `圖片 · ${t.label}${t.fursona_name ? `（${t.fursona_name}）` : ''}`
    case 'fursona': return `獸設 · ${t.label}`
    default: return `用戶 · ${t.label}`
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
          管理後台
        </h1>
        <p>檢舉佇列與操作紀錄 · 僅站方可見</p>
      </div>
      <span class="sp" /><span class="pill danger">admin</span>
    </div>

    <div
      v-if="stats"
      class="kpi"
    >
      <div class="card">
        <b class="disp">{{ stats.open }}</b><span>待處理檢舉</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.open_illegal }}</b><span>紅線內容（優先）</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.resolved_this_week }}</b><span>本週已處理</span>
      </div>
      <div class="card">
        <b class="disp">{{ stats.banned_users }}</b><span>停權帳號</span>
      </div>
    </div>

    <div class="card">
      <div class="hd">
        <span class="disp">檢舉佇列</span>
        <div class="seg sm">
          <button
            type="button"
            :class="{ on: status === 'open' }"
            @click="status = 'open'"
          >
            待處理
          </button>
          <button
            type="button"
            :class="{ on: status === 'resolved' }"
            @click="status = 'resolved'"
          >
            已處理
          </button>
          <button
            type="button"
            :class="{ on: status === 'dismissed' }"
            @click="status = 'dismissed'"
          >
            已駁回
          </button>
          <button
            type="button"
            :class="{ on: status === 'all' }"
            @click="status = 'all'"
          >
            全部
          </button>
        </div>
        <em>紅線內容優先，其餘依時間</em>
      </div>
      <div class="bd tblwrap">
        <table
          v-if="reports?.data.length"
          class="tbl"
        >
          <thead>
            <tr><th>目標</th><th>原因</th><th>檢舉者</th><th>時間</th><th>狀態</th><th /></tr>
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
                  擁有者 <NuxtLink
                    :to="`/u/${r.target.owner_pawfit_id}`"
                    class="link"
                  >@{{ r.target.owner_pawfit_id }}</NuxtLink>
                  <template v-if="r.target_type === 'media'">
                    · {{ r.target.is_nsfw ? 'NSFW' : 'SFW' }} · {{ r.target.status }}
                  </template>
                  <template v-if="r.target_type === 'fursona' && r.target.removed_at">
                    · 已下架
                  </template>
                  <template v-if="r.target_type === 'profile' && r.target.is_banned">
                    · 已停權
                  </template>
                </div>
                <div
                  v-if="r.detail"
                  class="sub"
                  style="font-size:12px;max-width:36ch"
                >
                  「{{ r.detail }}」
                </div>
              </td>
              <td>
                <span
                  class="pill"
                  :class="REASON_PILL[r.reason_code]"
                >{{ REASON_LABEL[r.reason_code] }}</span>
              </td>
              <td>@{{ r.reporter?.pawfit_id ?? '?' }}</td>
              <td class="mono">
                {{ formatDate(r.created_at, true) }}
              </td>
              <td>
                <span
                  class="pill"
                  :class="r.status === 'open' ? 'warn' : r.status === 'resolved' ? 'ok' : ''"
                >{{ r.status === 'open' ? '待處理' : r.status === 'resolved' ? '已處理' : '已駁回' }}</span>
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
                      預覽
                    </button>
                    <button
                      v-if="!r.target.is_nsfw"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'mark_nsfw', r.target_id)"
                    >
                      改標 NSFW
                    </button>
                    <button
                      v-if="r.target.status === 'active'"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'remove_media', r.target_id)"
                    >
                      下架
                    </button>
                    <button
                      v-if="r.reason_code === 'illegal'"
                      class="btn sm danger"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'purge_media', r.target_id, '紅線內容緊急移除')"
                    >
                      緊急移除
                    </button>
                  </template>
                  <template v-else-if="r.target_type === 'fursona'">
                    <NuxtLink
                      v-if="r.target.fursona_id"
                      class="btn sm"
                      :to="`/fursona/${r.target.fursona_id}`"
                      target="_blank"
                    >
                      檢視
                    </NuxtLink>
                    <button
                      v-if="!r.target.removed_at"
                      class="btn sm"
                      type="button"
                      :disabled="busyId === r.id"
                      @click="act(r, 'remove_fursona', r.target_id)"
                    >
                      下架
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
                      停權
                    </button>
                  </template>
                  <button
                    class="btn sm ghost"
                    type="button"
                    :disabled="busyId === r.id"
                    @click="act(r, 'dismiss_report', r.id)"
                  >
                    駁回
                  </button>
                </div>
                <div
                  v-else-if="r.status !== 'open'"
                  class="muted"
                  style="font-size:12px"
                >
                  {{ r.resolved_by ? `由 @${r.resolved_by.pawfit_id}` : '' }} {{ formatDate(r.resolved_at, true) }}
                </div>
                <div
                  v-else
                  class="row"
                  style="gap:6px"
                >
                  <button
                    class="btn sm ghost"
                    type="button"
                    @click="act(r, 'resolve_report', r.id, '目標已不存在')"
                  >
                    結案
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
          沒有符合的檢舉。
        </div>
      </div>
    </div>

    <div
      v-if="stats"
      class="card"
      style="margin-top:22px"
    >
      <h2 class="disp">
        操作紀錄
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
          <span>{{ a.admin ?? 'admin' }} · {{ ADMIN_ACTION_LABEL[a.action] ?? a.action }} <span class="mono">{{ a.target_type }}/{{ a.target_id.slice(0, 8) }}</span><template v-if="a.note">（{{ a.note }}）</template></span>
        </div>
        <div
          v-if="!stats.recent_actions.length"
          class="muted"
        >
          尚無紀錄。
        </div>
      </div>
    </div>

    <PawDialog
      :open="!!preview"
      title="內容預覽"
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
          關閉
        </button>
      </template>
    </PawDialog>
  </section>
</template>
