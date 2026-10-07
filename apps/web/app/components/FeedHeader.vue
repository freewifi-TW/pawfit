<script setup lang="ts">
/** 河道頁共用標頭：好友／探索切換＋發文按鈕。 */
defineProps<{ active: 'friends' | 'explore' }>()
const { t } = useI18n()
const { me } = useAuth()
const { features } = useFeatures()
</script>

<template>
  <div class="pagehd">
    <div>
      <h1 class="disp">
        {{ active === 'friends' ? t('feed.friends.title') : t('feed.explore.title') }}
      </h1>
      <p>{{ active === 'friends' ? t('feed.friends.subtitle') : t('feed.explore.subtitle') }}</p>
    </div>
    <span class="sp" />
    <nav
      class="row"
      style="gap:6px"
    >
      <NuxtLink
        v-if="me?.is_onboarded"
        class="btn sm"
        :class="{ primary: active === 'friends' }"
        to="/feed"
      >
        {{ t('feed.tabs.friends') }}
      </NuxtLink>
      <NuxtLink
        class="btn sm"
        :class="{ primary: active === 'explore' }"
        to="/feed/explore"
      >
        {{ t('feed.tabs.explore') }}
      </NuxtLink>
      <NuxtLink
        v-if="me?.is_onboarded"
        class="btn sm primary"
        to="/post/new"
        style="margin-left:8px"
      >
        ＋ {{ t('feed.newPost') }}
      </NuxtLink>
      <NuxtLink
        v-if="me?.is_onboarded && features.head_sticker"
        class="btn sm"
        to="/post/new/head-sticker"
        :title="t('headsticker.subtitle')"
      >
        🐾 {{ t('headsticker.title') }}
      </NuxtLink>
    </nav>
  </div>
</template>
