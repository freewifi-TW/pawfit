<script setup lang="ts">
import type { ReportReason, ReportTargetType } from '~/types/api'
import { REASON_LABEL } from '~/utils/labels'

/** 檢舉對話框（FR-5.1）；未登入者導向登入。 */
const open = defineModel<boolean>('open', { default: false })
const props = defineProps<{
  targetType: ReportTargetType
  targetId: string
  targetLabel: string
}>()

const api = useApi()
const notify = useNotify()
const { me } = useAuth()
const route = useRoute()

const reason = ref<ReportReason>('illegal')
const detail = ref('')
const busy = ref(false)

const reasons: Array<{ value: ReportReason, hint: string }> = [
  { value: 'illegal', hint: '涉及未成年、真實暴力等紅線內容，會優先處理。' },
  { value: 'untagged_nsfw', hint: '成人內容但標為 SFW。' },
  { value: 'copyright', hint: '未經授權使用他人作品。' },
  { value: 'harassment', hint: '' },
  { value: 'other', hint: '' }
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
    notify.ok('已送出檢舉', '站方會盡快處理。')
    open.value = false
    detail.value = ''
  } catch (e) {
    notify.err('送出失敗', apiError(e).message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <PawDialog
    v-model:open="open"
    :title="`檢舉${targetLabel}`"
    description="檢舉會由站方人工審核；惡意檢舉可能導致停權。"
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
          <b>{{ REASON_LABEL[r.value] }}</b>
          <small v-if="r.hint">{{ r.hint }}</small>
        </span>
      </label>
    </div>
    <div
      class="field"
      style="margin:0"
    >
      <label for="report-detail">補充說明（選填）</label>
      <textarea
        id="report-detail"
        v-model="detail"
        class="input"
        style="min-height:72px"
        maxlength="2000"
        placeholder="提供連結或說明有助於加速處理"
      />
    </div>
    <template #footer>
      <button
        class="btn"
        type="button"
        @click="open = false"
      >
        取消
      </button>
      <button
        class="btn primary"
        type="button"
        :disabled="busy"
        @click="submit"
      >
        {{ me ? '送出檢舉' : '登入後檢舉' }}
      </button>
    </template>
  </PawDialog>
</template>
