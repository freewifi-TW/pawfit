<script setup lang="ts">
import type { ReportReason, ReportTargetType } from '~/types/api'

/** 檢舉對話框（FR-5.1）；未登入者導向登入。 */
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{
  targetType: ReportTargetType
  targetId: string
  targetLabel: string
}>()

const { t } = useI18n()
const { reasonLabel } = useLabels()
const api = useApi()
const notify = useNotify()
const { me } = useAuth()
const route = useRoute()

const reason = ref<ReportReason>('illegal')
const detail = ref('')
const busy = ref(false)

const reasons: Array<{ value: ReportReason, hintKey?: string }> = [
  { value: 'illegal', hintKey: 'admin.report.hints.illegal' },
  { value: 'untagged_nsfw', hintKey: 'admin.report.hints.untaggedNsfw' },
  { value: 'copyright', hintKey: 'admin.report.hints.copyright' },
  { value: 'harassment' },
  { value: 'other' }
]

async function submit() {
  if (!me.value) {
    open.value = false
    return navigateTo({ path: '/login', query: { next: route.fullPath } })
  }
  busy.value = true
  try {
    await api('/reports', {
      method: 'POST',
      body: { target_type: props.targetType, target_id: props.targetId, reason_code: reason.value, detail: detail.value || null }
    })
    notify.ok(t('admin.report.notify.sent'), t('admin.report.notify.sentHint'))
    open.value = false
    detail.value = ''
  } catch (e) {
    notify.err(t('admin.report.notify.failed'), apiError(e).message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PawDialog
    v-model:open="open"
    :title="t('admin.report.title', { target: targetLabel })"
    :description="t('admin.report.description')"
  >
    <div
      class="radio-list"
      style="margin-bottom:14px"
    >
      <label
        v-for="r in reasons"
        :key="r.value"
        class="radio"
      >
        <input
          v-model="reason"
          type="radio"
          name="report-reason"
          :value="r.value"
        >
        <span>
          <b>{{ reasonLabel(r.value) }}</b>
          <small v-if="r.hintKey">{{ t(r.hintKey) }}</small>
        </span>
      </label>
    </div>
    <div
      class="field"
      style="margin:0"
    >
      <label for="report-detail">{{ t('admin.report.detailLabel') }}</label>
      <textarea
        id="report-detail"
        v-model="detail"
        class="input"
        style="min-height:72px"
        maxlength="2000"
        :placeholder="t('admin.report.detailPlaceholder')"
      />
    </div>
    <template #footer>
      <button
        class="btn"
        type="button"
        @click="open = false"
      >
        {{ t('admin.report.cancel') }}
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="submit"
      >
        {{ me ? t('admin.report.submit') : t('admin.report.loginToReport') }}
      </button>
    </template>
  </PawDialog>
</template>
