<script setup lang="ts">
import type { ProfilePage } from '~/types/api'
import { paletteGradient } from '~/utils/labels'

const route = useRoute()
const api = useApi()
const notify = useNotify()
const config = useRuntimeConfig()
const { t } = useI18n()
const { formatMonth } = useLabels()
const pawfitId = route.params.pawfitId as string

const { data: page, error } = await useAsyncData(`profile-${pawfitId}`, () => api<ProfilePage>(`/users/${encodeURIComponent(pawfitId)}`))
if (error.value || !page.value) {
  throw createError({ statusCode: 404, statusMessage: t('profile.notFound') })
}

const user = computed(() => page.value!.user)
const rep = computed(() => page.value!.representative)

useSeoMeta({
  title: () => t('profile.seo.title', { name: user.value.display_name, id: user.value.pawfit_id }),
  description: () => t('profile.seo.description', {
    name: user.value.display_name,
    list: page.value!.fursonas.map(f => f.name).join(t('profile.seo.listSeparator')) || t('profile.seo.noPublic')
  }),
  ogTitle: () => t('profile.seo.ogTitle', { name: user.value.display_name }),
  ogImage: () => rep.value?.cover_url ? `${config.public.siteUrl}${rep.value.cover_url}` : undefined,
  ogUrl: () => `${config.public.siteUrl}/u/${user.value.pawfit_id}`
})

// 可嵌入時輸出 oEmbed discovery link（FR-7.2；指向代表獸設的卡片）
useHead(() => ({
  link: page.value?.embed_enabled && rep.value
    ? [{
        rel: 'alternate',
        type: 'application/json+oembed',
        href: `${config.public.siteUrl}/api/oembed?format=json&url=${encodeURIComponent(`${config.public.siteUrl}/u/${user.value.pawfit_id}`)}`,
        title: `${user.value.display_name} · Pawfit`
      }]
    : []
}))

const reporting = ref(false)

/* ---- Phase 2 M1：加好友／封鎖（只在好友功能啟用且登入時顯示） ---- */
const api2 = useApi()
const { friends: friendsEnabled } = useFeatures()
const { me } = useAuth()
const relation = computed(() => page.value?.relation ?? null)
const busy = ref(false)
const confirmBlock = ref(false)
const confirmRemove = ref(false)

async function social(fn: () => Promise<unknown>, okMsg: string) {
  busy.value = true
  try {
    await fn()
    await refreshNuxtData(`profile-${pawfitId}`)
    notify.ok(okMsg)
  } catch (e) {
    const err = apiError(e)
    notify.err(t('friends.notify.failed'), Object.values(err.errors)[0] || err.message)
  } finally {
    busy.value = false
    confirmBlock.value = false
    confirmRemove.value = false
  }
}
const addFriend = () => social(() => api2('/friends/requests', { method: 'POST', body: { pawfit_id: user.value.pawfit_id } }), t('friends.notify.sent', { id: user.value.pawfit_id }))
const cancelRequest = () => social(() => api2(`/friends/requests/${relation.value?.friendship_id}`, { method: 'DELETE' }), t('friends.notify.cancelled'))
const acceptRequest = () => social(() => api2(`/friends/requests/${relation.value?.friendship_id}/accept`, { method: 'POST' }), t('friends.notify.accepted', { id: user.value.pawfit_id }))
const removeFriend = () => social(() => api2(`/friends/${user.value.id}`, { method: 'DELETE' }), t('friends.notify.removed', { id: user.value.pawfit_id }))
async function block() {
  busy.value = true
  try {
    await api2(`/blocks/${user.value.id}`, { method: 'POST' })
    notify.ok(t('friends.notify.blocked', { id: user.value.pawfit_id }))
    await navigateTo('/friends')
  } catch (e) {
    notify.err(t('friends.notify.failed'), apiError(e).message)
    busy.value = false
    confirmBlock.value = false
  }
}
</script>

<template>
  <section
    v-if="page"
    class="wrap"
    style="padding-bottom:48px"
  >
    <div class="prof">
      <BlobAvatar
        :src="user.avatar_url"
        :size="120"
        variant="user"
        :alt="user.display_name"
      />
      <div>
        <h1 class="disp">
          {{ user.display_name }}
        </h1>
        <div class="id">
          @{{ user.pawfit_id }} · {{ t('profile.header.joined', { month: formatMonth(user.joined_at) }) }} ·
          <template v-if="page.is_owner && page.stats.total_count !== null">
            {{ t('profile.header.statsOwner', { total: page.stats.total_count, publicCount: page.stats.public_count }, page.stats.total_count) }}
          </template>
          <template v-else>
            {{ t('profile.header.statsPublic', page.stats.public_count) }}
          </template>
        </div>
      </div>
      <div class="row">
        <NuxtLink
          v-if="page.is_owner"
          class="btn"
          to="/dashboard"
        >
          {{ t('profile.actions.manage') }}
        </NuxtLink>
        <template v-else>
          <template v-if="friendsEnabled && me && relation">
            <button
              v-if="relation.status === 'none'"
              class="btn primary"
              type="button"
              :disabled="busy"
              @click="addFriend"
            >
              {{ t('friends.profile.addFriend') }}
            </button>
            <template v-else-if="relation.status === 'pending_out'">
              <span class="pill warn">{{ t('friends.profile.pendingOut') }}</span>
              <button
                class="btn sm ghost"
                type="button"
                :disabled="busy"
                @click="cancelRequest"
              >
                {{ t('friends.profile.cancel') }}
              </button>
            </template>
            <button
              v-else-if="relation.status === 'pending_in'"
              class="btn primary"
              type="button"
              :disabled="busy"
              @click="acceptRequest"
            >
              {{ t('friends.profile.acceptRequest') }}
            </button>
            <template v-else>
              <span class="pill ok">{{ t('friends.profile.friends') }}</span>
              <button
                v-if="!confirmRemove"
                class="btn sm ghost"
                type="button"
                :disabled="busy"
                @click="confirmRemove = true"
              >
                {{ t('friends.profile.remove') }}
              </button>
              <button
                v-else
                class="btn sm danger"
                type="button"
                :disabled="busy"
                @click="removeFriend"
              >
                {{ t('friends.removeConfirm') }}
              </button>
            </template>
            <button
              v-if="!confirmBlock"
              class="btn ghost sm"
              type="button"
              :title="t('friends.profile.blockHint')"
              :disabled="busy"
              @click="confirmBlock = true"
            >
              {{ t('friends.profile.block') }}
            </button>
            <button
              v-else
              class="btn sm danger"
              type="button"
              :disabled="busy"
              @click="block"
            >
              {{ t('friends.profile.blockConfirm') }}
            </button>
          </template>
          <NuxtLink
            v-else-if="friendsEnabled && !me"
            class="btn"
            to="/login"
          >
            {{ t('friends.profile.addFriend') }}
          </NuxtLink>
          <button
            class="btn ghost sm"
            type="button"
            @click="reporting = true"
          >
            {{ t('profile.actions.report') }}
          </button>
        </template>
      </div>
    </div>

    <div
      v-if="rep"
      class="card rep"
    >
      <div
        class="art"
        :style="rep.cover_url ? '' : `background:${paletteGradient(rep.palette)}`"
      >
        <img
          v-if="rep.cover_url"
          :src="rep.cover_url"
          :alt="rep.name"
        >
      </div>
      <div
        class="bd"
        style="padding:26px"
      >
        <span
          class="pill accent"
          style="justify-self:start"
        >{{ t('profile.rep.badge') }}</span>
        <h2
          class="disp"
          style="margin:0;font-size:32px"
        >
          {{ rep.name }}
        </h2>
        <div
          v-if="rep.species || rep.tags.length"
          class="sub"
        >
          {{ [rep.species, ...rep.tags.slice(0, 2)].filter(Boolean).join(' · ') }}
        </div>
        <div
          v-if="rep.palette.length"
          class="row"
          style="gap:6px"
        >
          <i
            v-for="p in rep.palette.slice(0, 4)"
            :key="p.hex"
            class="swatch-mini"
            :style="`background:${p.hex}`"
          />
          <span
            v-if="rep.palette.length > 4"
            class="muted"
            style="font-size:12px;margin-left:4px"
          >{{ t('profile.rep.moreColors', { n: rep.palette.length - 4 }) }}</span>
        </div>
        <div class="row">
          <NuxtLink
            v-if="rep.share_link"
            class="btn primary"
            :to="`/s/${rep.share_link.slug}`"
          >
            {{ t('profile.rep.viewFull') }}
          </NuxtLink>
          <NuxtLink
            v-else-if="page.is_owner"
            class="btn primary"
            :to="`/fursona/${rep.id}#privacy`"
          >
            {{ t('profile.rep.createShareLink') }}
          </NuxtLink>
          <span
            class="muted"
            style="font-size:12px"
          >{{ t('profile.rep.mediaCount', rep.media_count ?? 0) }}</span>
        </div>
      </div>
    </div>

    <div
      class="pagehd"
      style="padding-top:30px"
    >
      <div>
        <h2
          class="disp"
          style="margin:0;font-size:24px"
        >
          {{ t('profile.list.title') }}
        </h2>
      </div>
      <span class="sp" />
      <span
        class="muted"
        style="font-size:12px"
      >{{ t('profile.list.hint') }}</span>
    </div>
    <div
      v-if="page.fursonas.length"
      class="sonas"
    >
      <FursonaCard
        v-for="f in page.fursonas"
        :key="f.id"
        :fursona="f"
      >
        <template #actions>
          <NuxtLink
            v-if="f.share_link"
            class="btn sm"
            :to="`/s/${f.share_link.slug}`"
          >
            {{ t('profile.list.view') }}
          </NuxtLink>
          <NuxtLink
            v-else-if="page.is_owner"
            class="btn sm"
            :to="`/fursona/${f.id}`"
          >
            {{ t('profile.list.edit') }}
          </NuxtLink>
          <span
            v-else
            class="muted"
            style="font-size:12px"
          >{{ t('profile.list.noSharePage') }}</span>
        </template>
      </FursonaCard>
    </div>
    <div
      v-else
      class="empty"
    >
      {{ page.is_owner ? t('profile.list.emptyOwner') : t('profile.list.emptyVisitor') }}
    </div>

    <ReportDialog
      v-model:open="reporting"
      target-type="profile"
      :target-id="user.id"
      :target-label="t('profile.reportTarget', { id: user.pawfit_id })"
    />
  </section>
</template>
