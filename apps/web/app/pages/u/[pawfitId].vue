<script setup lang="ts">
import type { ProfilePage } from '~/types/api'
import { formatMonth, paletteGradient } from '~/utils/labels'

const route = useRoute()
const api = useApi()
const notify = useNotify()
const config = useRuntimeConfig()
const pawfitId = route.params.pawfitId as string

const { data: page, error } = await useAsyncData(`profile-${pawfitId}`, () => api<ProfilePage>(`/users/${encodeURIComponent(pawfitId)}`))
if (error.value || !page.value) {
  throw createError({ statusCode: 404, statusMessage: '找不到這位用戶' })
}

const user = computed(() => page.value!.user)
const rep = computed(() => page.value!.representative)

useSeoMeta({
  title: () => `${user.value.display_name}（@${user.value.pawfit_id}）`,
  description: () => `${user.value.display_name} 在 Pawfit 的獸設：${page.value!.fursonas.map(f => f.name).join('、') || '尚未公開任何獸設'}`,
  ogTitle: () => `${user.value.display_name} · Pawfit`,
  ogImage: () => rep.value?.cover_url ? `${config.public.siteUrl}${rep.value.cover_url}` : undefined,
  ogUrl: () => `${config.public.siteUrl}/u/${user.value.pawfit_id}`
})

const reporting = ref(false)
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
          @{{ user.pawfit_id }} · {{ formatMonth(user.joined_at) }}加入 ·
          <template v-if="page.is_owner && page.stats.total_count !== null">
            {{ page.stats.total_count }} 隻獸設（{{ page.stats.public_count }} 隻公開）
          </template>
          <template v-else>
            {{ page.stats.public_count }} 隻公開獸設
          </template>
        </div>
      </div>
      <div class="row">
        <NuxtLink
          v-if="page.is_owner"
          class="btn"
          to="/dashboard"
        >
          管理我的獸設
        </NuxtLink>
        <template v-else>
          <button
            class="btn"
            type="button"
            @click="notify.info('好友功能將於 Phase 2 推出')"
          >
            ＋ 加好友
          </button>
          <button
            class="btn ghost sm"
            type="button"
            @click="reporting = true"
          >
            檢舉
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
        >★ 代表獸設</span>
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
          >＋{{ rep.palette.length - 4 }} 色</span>
        </div>
        <div class="row">
          <NuxtLink
            v-if="rep.share_link"
            class="btn primary"
            :to="`/s/${rep.share_link.slug}`"
          >
            看完整設定
          </NuxtLink>
          <NuxtLink
            v-else-if="page.is_owner"
            class="btn primary"
            :to="`/fursona/${rep.id}#privacy`"
          >
            產生分享連結
          </NuxtLink>
          <span
            class="muted"
            style="font-size:12px"
          >{{ rep.media_count ?? 0 }} 張設定圖</span>
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
          公開獸設
        </h2>
      </div>
      <span class="sp" />
      <span
        class="muted"
        style="font-size:12px"
      >私人與連結可見的獸設不會列在這裡</span>
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
            看設定
          </NuxtLink>
          <NuxtLink
            v-else-if="page.is_owner"
            class="btn sm"
            :to="`/fursona/${f.id}`"
          >
            編輯
          </NuxtLink>
          <span
            v-else
            class="muted"
            style="font-size:12px"
          >尚未開放分享頁</span>
        </template>
      </FursonaCard>
    </div>
    <div
      v-else
      class="empty"
    >
      {{ page.is_owner ? '你還沒有公開的獸設。到「我的獸設」把隱私改成公開，就會出現在這裡。' : '這位用戶還沒有公開的獸設。' }}
    </div>

    <ReportDialog
      v-model:open="reporting"
      target-type="profile"
      :target-id="user.id"
      :target-label="`用戶 @${user.pawfit_id}`"
    />
  </section>
</template>
