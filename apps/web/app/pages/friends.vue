<script setup lang="ts">
import type { FriendsPage } from '~/types/api'

/** 好友列表與待處理邀請（Phase 2 FR-B1.3）：以 Pawfit ID 送出邀請、接受／拒絕、解除、封鎖管理。 */
definePageMeta({ middleware: 'onboarded' })

const { t } = useI18n()
const api = useApi()
const notify = useNotify()
const { formatDate } = useLabels()
const { friends: enabled } = useFeatures()

if (!enabled.value) {
  throw createError({ statusCode: 404, statusMessage: t('friends.disabled') })
}

useSeoMeta({ title: () => t('friends.title') })

const { data: page, refresh } = await useAsyncData('friends', () => api<FriendsPage>('/friends'), {
  default: () => ({ friends: [], incoming: [], outgoing: [], blocked: [] })
})

const pawfitId = ref('')
const busy = ref(false)
const tab = ref<'friends' | 'requests' | 'blocked'>('friends')
const confirmRemove = ref<string | null>(null)

async function run(fn: () => Promise<unknown>, okMsg: string) {
  busy.value = true
  try {
    await fn()
    await refresh()
    notify.ok(okMsg)
    return true
  } catch (e) {
    const err = apiError(e)
    notify.err(t('friends.notify.failed'), Object.values(err.errors)[0] || err.message)
    return false
  } finally {
    busy.value = false
  }
}

async function sendRequest() {
  const id = pawfitId.value.trim().replace(/^@/, '')
  if (!id) return
  const ok = await run(async () => {
    const res = await api<{ status: string }>('/friends/requests', { method: 'POST', body: { pawfit_id: id } })
    if (res.status === 'friends') notify.ok(t('friends.notify.nowFriends', { id }))
  }, t('friends.notify.sent', { id }))
  if (ok) pawfitId.value = ''
}

const accept = (fid: string, id: string) => run(() => api(`/friends/requests/${fid}/accept`, { method: 'POST' }), t('friends.notify.accepted', { id }))
const decline = (fid: string) => run(() => api(`/friends/requests/${fid}`, { method: 'DELETE' }), t('friends.notify.declined'))
const cancel = (fid: string) => run(() => api(`/friends/requests/${fid}`, { method: 'DELETE' }), t('friends.notify.cancelled'))
const unblock = (userId: string, id: string) => run(() => api(`/blocks/${userId}`, { method: 'DELETE' }), t('friends.notify.unblocked', { id }))
async function remove(userId: string, id: string) {
  await run(() => api(`/friends/${userId}`, { method: 'DELETE' }), t('friends.notify.removed', { id }))
  confirmRemove.value = null
}

const pendingCount = computed(() => page.value.incoming.length)
</script>

<template>
  <section
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="pagehd">
      <div>
        <h1 class="disp">
          {{ t('friends.title') }}
        </h1>
        <p>{{ t('friends.subtitle') }}</p>
      </div>
    </div>

    <div
      class="card"
      style="margin-bottom:22px"
    >
      <h2 class="disp">
        {{ t('friends.add.title') }}
      </h2>
      <div class="bd">
        <form
          class="row"
          @submit.prevent="sendRequest"
        >
          <div
            class="prefix"
            style="flex:1;min-width:220px"
          >
            <span>@</span>
            <input
              v-model="pawfitId"
              :placeholder="t('friends.add.placeholder')"
              maxlength="20"
              autocomplete="off"
            >
          </div>
          <button
            class="btn primary"
            type="submit"
            :disabled="busy || !pawfitId.trim()"
          >
            {{ t('friends.add.submit') }}
          </button>
        </form>
        <p
          class="muted"
          style="font-size:12px;margin:8px 0 0"
        >
          {{ t('friends.add.hint') }}
        </p>
      </div>
    </div>

    <div
      class="tabs"
      role="tablist"
    >
      <button
        type="button"
        role="tab"
        :class="{ on: tab === 'friends' }"
        @click="tab = 'friends'"
      >
        {{ t('friends.tabs.friends') }} <span class="muted">{{ page.friends.length }}</span>
      </button>
      <button
        type="button"
        role="tab"
        :class="{ on: tab === 'requests' }"
        @click="tab = 'requests'"
      >
        {{ t('friends.tabs.requests') }} <span
          v-if="pendingCount"
          class="pill accent"
          style="font-size:11px"
        >{{ pendingCount }}</span>
      </button>
      <button
        type="button"
        role="tab"
        :class="{ on: tab === 'blocked' }"
        @click="tab = 'blocked'"
      >
        {{ t('friends.tabs.blocked') }} <span class="muted">{{ page.blocked.length }}</span>
      </button>
    </div>

    <div
      v-if="tab === 'friends'"
      class="stack"
      style="gap:10px"
    >
      <div
        v-for="f in page.friends"
        :key="f.friendship_id"
        class="card person"
      >
        <NuxtLink
          class="who"
          :to="`/u/${f.user.pawfit_id}`"
        >
          <BlobAvatar
            :src="f.user.avatar_url"
            :size="44"
            :border="2"
            variant="user"
          />
          <span><b>{{ f.user.display_name }}</b><small>@{{ f.user.pawfit_id }} · {{ t('friends.since', { date: formatDate(f.accepted_at) }) }}</small></span>
        </NuxtLink>
        <span class="sp" />
        <template v-if="confirmRemove === f.user.id">
          <button
            class="btn sm danger"
            type="button"
            :disabled="busy"
            @click="remove(f.user.id, f.user.pawfit_id)"
          >
            {{ t('friends.removeConfirm') }}
          </button>
          <button
            class="btn sm ghost"
            type="button"
            @click="confirmRemove = null"
          >
            {{ t('fursona.actions.cancel') }}
          </button>
        </template>
        <button
          v-else
          class="btn sm ghost"
          type="button"
          :disabled="busy"
          @click="confirmRemove = f.user.id"
        >
          {{ t('friends.remove') }}
        </button>
      </div>
      <div
        v-if="!page.friends.length"
        class="empty"
      >
        {{ t('friends.empty.friends') }}
      </div>
    </div>

    <div
      v-else-if="tab === 'requests'"
      class="stack"
      style="gap:18px"
    >
      <div>
        <h3
          class="disp"
          style="margin:0 0 8px;font-size:16px"
        >
          {{ t('friends.incoming') }}
        </h3>
        <div
          class="stack"
          style="gap:10px"
        >
          <div
            v-for="r in page.incoming"
            :key="r.friendship_id"
            class="card person"
          >
            <NuxtLink
              class="who"
              :to="`/u/${r.user.pawfit_id}`"
            >
              <BlobAvatar
                :src="r.user.avatar_url"
                :size="44"
                :border="2"
                variant="user"
              />
              <span><b>{{ r.user.display_name }}</b><small>@{{ r.user.pawfit_id }} · {{ formatDate(r.created_at) }}</small></span>
            </NuxtLink>
            <span class="sp" />
            <button
              class="btn sm primary"
              type="button"
              :disabled="busy"
              @click="accept(r.friendship_id, r.user.pawfit_id)"
            >
              {{ t('friends.accept') }}
            </button>
            <button
              class="btn sm ghost"
              type="button"
              :disabled="busy"
              @click="decline(r.friendship_id)"
            >
              {{ t('friends.decline') }}
            </button>
          </div>
          <div
            v-if="!page.incoming.length"
            class="empty"
          >
            {{ t('friends.empty.incoming') }}
          </div>
        </div>
      </div>
      <div>
        <h3
          class="disp"
          style="margin:0 0 8px;font-size:16px"
        >
          {{ t('friends.outgoing') }}
        </h3>
        <div
          class="stack"
          style="gap:10px"
        >
          <div
            v-for="r in page.outgoing"
            :key="r.friendship_id"
            class="card person"
          >
            <NuxtLink
              class="who"
              :to="`/u/${r.user.pawfit_id}`"
            >
              <BlobAvatar
                :src="r.user.avatar_url"
                :size="44"
                :border="2"
                variant="user"
              />
              <span><b>{{ r.user.display_name }}</b><small>@{{ r.user.pawfit_id }} · {{ formatDate(r.created_at) }}</small></span>
            </NuxtLink>
            <span class="sp" />
            <span class="pill warn">{{ t('friends.pending') }}</span>
            <button
              class="btn sm ghost"
              type="button"
              :disabled="busy"
              @click="cancel(r.friendship_id)"
            >
              {{ t('friends.cancel') }}
            </button>
          </div>
          <div
            v-if="!page.outgoing.length"
            class="empty"
          >
            {{ t('friends.empty.outgoing') }}
          </div>
        </div>
      </div>
    </div>

    <div
      v-else
      class="stack"
      style="gap:10px"
    >
      <div
        v-for="b in page.blocked"
        :key="b.user.id"
        class="card person"
      >
        <span class="who">
          <BlobAvatar
            :src="b.user.avatar_url"
            :size="44"
            :border="2"
            variant="user"
          />
          <span><b>{{ b.user.display_name }}</b><small>@{{ b.user.pawfit_id }} · {{ formatDate(b.created_at) }}</small></span>
        </span>
        <span class="sp" />
        <button
          class="btn sm ghost"
          type="button"
          :disabled="busy"
          @click="unblock(b.user.id, b.user.pawfit_id)"
        >
          {{ t('friends.unblock') }}
        </button>
      </div>
      <div
        v-if="!page.blocked.length"
        class="empty"
      >
        {{ t('friends.empty.blocked') }}
      </div>
      <p
        class="muted"
        style="font-size:12px;margin:0"
      >
        {{ t('friends.blockedNote') }}
      </p>
    </div>
  </section>
</template>

<style scoped>
.person { display: flex; align-items: center; gap: 12px; padding: 12px 16px; flex-wrap: wrap; }
.who { display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; min-width: 0; }
.who span { display: grid; line-height: 1.3; min-width: 0; }
.who b { font-size: 15px; }
.who small { color: var(--ink-3); font-size: 12px; }
.tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; }
.tabs button { border: var(--border) solid var(--line); background: var(--paper); border-radius: var(--r-pill); padding: 6px 14px; font-weight: 700; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; }
.tabs button.on { background: var(--accent-soft); border-color: var(--accent); color: var(--accent-deep); }
</style>
