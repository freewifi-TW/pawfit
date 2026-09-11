<script setup lang="ts">
import type { Fursona } from '~/types/api'
import { formatBytes } from '~/utils/labels'

definePageMeta({ middleware: 'onboarded' })
useSeoMeta({ title: '我的獸設' })

const api = useApi()
const notify = useNotify()
const route = useRoute()
const { me, fetchMe } = useAuth()

const { data: fursonas, refresh } = await useAsyncData('my-fursonas', () => api<Fursona[]>('/fursonas'), { default: () => [] })

const quota = computed(() => me.value?.quota)
const storagePct = computed(() => quota.value ? Math.min(100, Math.round((quota.value.storage_used / quota.value.storage_limit) * 100)) : 0)
const dailyPct = computed(() => quota.value ? Math.min(100, Math.round((quota.value.uploads_today / quota.value.daily_limit) * 100)) : 0)

// 新增獸設
const creating = ref(false)
const form = reactive({ name: '', species: '' })
const busy = ref(false)
const errors = ref<Record<string, string>>({})

onMounted(() => {
  if (route.query.new && fursonas.value.length === 0) creating.value = true
})

async function create() {
  errors.value = {}
  if (!form.name.trim()) {
    errors.value.name = '請填名字。'
    return
  }
  busy.value = true
  try {
    const f = await api<Fursona>('/fursonas', { method: 'POST', body: { name: form.name.trim(), species: form.species.trim() || null } })
    notify.ok(`已建立 ${f.name}`, '接著上傳設定圖、填色票吧。')
    creating.value = false
    form.name = ''
    form.species = ''
    await refresh()
    await fetchMe(true)
    await navigateTo(`/fursona/${f.id}`)
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    if (!Object.keys(err.errors).length) notify.err('建立失敗', err.message)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="wrap">
    <div class="pagehd">
      <div>
        <h1 class="disp">
          我的獸設
        </h1>
        <p>{{ fursonas.length }} 隻獸設 · 代表獸設會顯示在你的個人主頁</p>
      </div>
      <span class="sp" />
      <button
        class="btn primary"
        type="button"
        @click="creating = true"
      >
        ＋ 新增獸設
      </button>
    </div>

    <div class="sonas">
      <FursonaCard
        v-for="f in fursonas"
        :key="f.id"
        :fursona="f"
        owner
      />
      <button
        class="card sona new"
        type="button"
        @click="creating = true"
      >
        <div>
          <b>＋</b>
          <div style="margin-top:8px;font-weight:700;color:var(--ink-2)">
            新增獸設
          </div>
          <div style="font-size:12px">
            名字、物種、色票，兩分鐘搞定
          </div>
        </div>
      </button>
    </div>

    <div
      v-if="quota"
      class="quota"
    >
      <div class="card">
        <div class="hd">
          <span class="disp">儲存空間</span><em class="mono">{{ formatBytes(quota.storage_used) }} / {{ formatBytes(quota.storage_limit) }}</em>
        </div>
        <div class="bd">
          <div class="bar">
            <i :style="`width:${storagePct}%`" />
          </div>
          <p
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            原檔會完整保留，展示版與縮圖不計入配額。
          </p>
        </div>
      </div>
      <div class="card">
        <div class="hd">
          <span class="disp">今日上傳</span><em class="mono">{{ quota.uploads_today }} / {{ quota.daily_limit }} 張</em>
        </div>
        <div class="bd">
          <div class="bar">
            <i :style="`width:${dailyPct}%`" />
          </div>
          <p
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            每日上限用來防止濫用，午夜重置。
          </p>
        </div>
      </div>
    </div>

    <PawDialog
      v-model:open="creating"
      title="新增獸設"
      description="先給名字就好，其他資料進編輯器再慢慢補。"
    >
      <form
        id="create-fursona"
        @submit.prevent="create"
      >
        <div class="field">
          <label for="nf-name">名字</label>
          <input
            id="nf-name"
            v-model="form.name"
            class="input"
            :class="{ 'is-invalid': errors.name }"
            maxlength="60"
            placeholder="阿燼 Ember"
            autofocus
          >
          <div
            v-if="errors.name"
            class="err"
          >
            {{ errors.name }}
          </div>
        </div>
        <div
          class="field"
          style="margin:0"
        >
          <label for="nf-species">物種（選填）</label>
          <input
            id="nf-species"
            v-model="form.species"
            class="input"
            maxlength="60"
            placeholder="赤狐"
          >
        </div>
      </form>
      <template #footer>
        <button
          class="btn"
          type="button"
          @click="creating = false"
        >
          取消
        </button>
        <button
          class="btn primary"
          type="submit"
          form="create-fursona"
          :disabled="busy"
        >
          建立
        </button>
      </template>
    </PawDialog>
  </section>
</template>
