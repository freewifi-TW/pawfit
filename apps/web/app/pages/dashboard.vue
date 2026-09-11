<script setup lang="ts">
import type { Fursona } from '~/types/api'
import { formatBytes } from '~/utils/labels'

definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()

useSeoMeta({ title: () => t('dashboard.title') })

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
    errors.value.name = t('dashboard.errors.nameRequired')
    return
  }
  busy.value = true
  try {
    const f = await api<Fursona>('/fursonas', { method: 'POST', body: { name: form.name.trim(), species: form.species.trim() || null } })
    notify.ok(t('dashboard.notify.created', { name: f.name }), t('dashboard.notify.createdHint'))
    creating.value = false
    form.name = ''
    form.species = ''
    await refresh()
    await fetchMe(true)
    await navigateTo(`/fursona/${f.id}`)
  } catch (e) {
    const err = apiError(e)
    errors.value = err.errors
    if (!Object.keys(err.errors).length) notify.err(t('dashboard.errors.createFailed'), err.message)
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
          {{ t('dashboard.title') }}
        </h1>
        <p>{{ t('dashboard.subtitle', fursonas.length) }}</p>
      </div>
      <span class="sp" />
      <button
        class="btn primary"
        type="button"
        @click="creating = true"
      >
        {{ t('dashboard.createButton') }}
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
            {{ t('dashboard.newCard.title') }}
          </div>
          <div style="font-size:12px">
            {{ t('dashboard.newCard.hint') }}
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
          <span class="disp">{{ t('dashboard.storage.title') }}</span><em class="mono">{{ formatBytes(quota.storage_used) }} / {{ formatBytes(quota.storage_limit) }}</em>
        </div>
        <div class="bd">
          <div class="bar">
            <i :style="`width:${storagePct}%`" />
          </div>
          <p
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            {{ t('dashboard.storage.hint') }}
          </p>
        </div>
      </div>
      <div class="card">
        <div class="hd">
          <span class="disp">{{ t('dashboard.daily.title') }}</span><em class="mono">{{ t('dashboard.daily.count', { used: quota.uploads_today, limit: quota.daily_limit }) }}</em>
        </div>
        <div class="bd">
          <div class="bar">
            <i :style="`width:${dailyPct}%`" />
          </div>
          <p
            class="muted"
            style="font-size:12px;margin:8px 0 0"
          >
            {{ t('dashboard.daily.hint') }}
          </p>
        </div>
      </div>
    </div>

    <PawDialog
      v-model:open="creating"
      :title="t('dashboard.dialog.title')"
      :description="t('dashboard.dialog.description')"
    >
      <form
        id="create-fursona"
        @submit.prevent="create"
      >
        <div class="field">
          <label for="nf-name">{{ t('dashboard.dialog.name') }}</label>
          <input
            id="nf-name"
            v-model="form.name"
            class="input"
            :class="{ 'is-invalid': errors.name }"
            maxlength="60"
            :placeholder="t('dashboard.dialog.namePlaceholder')"
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
          <label for="nf-species">{{ t('dashboard.dialog.species') }}</label>
          <input
            id="nf-species"
            v-model="form.species"
            class="input"
            maxlength="60"
            :placeholder="t('dashboard.dialog.speciesPlaceholder')"
          >
        </div>
      </form>
      <template #footer>
        <button
          class="btn"
          type="button"
          @click="creating = false"
        >
          {{ t('dashboard.dialog.cancel') }}
        </button>
        <button
          class="btn primary"
          type="submit"
          form="create-fursona"
          :disabled="busy"
        >
          {{ t('dashboard.dialog.submit') }}
        </button>
      </template>
    </PawDialog>
  </section>
</template>
