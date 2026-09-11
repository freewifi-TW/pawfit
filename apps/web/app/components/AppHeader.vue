<script setup lang="ts">
const { me, isAdmin, logout } = useAuth()
const menuOpen = ref(false)
</script>

<template>
  <header class="top">
    <div class="in">
      <PawLogo />
      <nav>
        <NuxtLink to="/">
          首頁
        </NuxtLink>
        <template v-if="me?.is_onboarded">
          <NuxtLink to="/dashboard">
            我的獸設
          </NuxtLink>
          <NuxtLink :to="`/u/${me.pawfit_id}`">
            個人主頁
          </NuxtLink>
          <NuxtLink to="/settings">
            設定
          </NuxtLink>
          <NuxtLink
            v-if="isAdmin"
            to="/admin"
          >
            管理
          </NuxtLink>
        </template>
      </nav>
      <span class="sp" />
      <UColorModeButton
        color="neutral"
        variant="ghost"
        size="sm"
      />
      <template v-if="me">
        <UPopover v-model:open="menuOpen">
          <button
            class="me"
            type="button"
          >
            <BlobAvatar
              :src="me.avatar_url"
              :size="26"
              variant="user"
            />
            <span>{{ me.pawfit_id ? `@${me.pawfit_id}` : (me.display_name || '未設定 ID') }}</span>
          </button>
          <template #content>
            <div
              class="stack"
              style="gap:4px;padding:8px;min-width:200px"
            >
              <NuxtLink
                v-if="me.is_onboarded"
                class="btn ghost"
                style="justify-content:flex-start"
                to="/dashboard"
                @click="menuOpen = false"
              >我的獸設</NuxtLink>
              <NuxtLink
                v-if="me.is_onboarded"
                class="btn ghost"
                style="justify-content:flex-start"
                :to="`/u/${me.pawfit_id}`"
                @click="menuOpen = false"
              >個人主頁</NuxtLink>
              <NuxtLink
                v-else
                class="btn ghost"
                style="justify-content:flex-start"
                to="/onboarding"
                @click="menuOpen = false"
              >完成設定</NuxtLink>
              <NuxtLink
                class="btn ghost"
                style="justify-content:flex-start"
                to="/settings"
                @click="menuOpen = false"
              >設定</NuxtLink>
              <NuxtLink
                v-if="isAdmin"
                class="btn ghost"
                style="justify-content:flex-start"
                to="/admin"
                @click="menuOpen = false"
              >管理後台</NuxtLink>
              <button
                class="btn ghost"
                style="justify-content:flex-start;color:var(--danger)"
                type="button"
                @click="menuOpen = false; logout()"
              >
                登出
              </button>
            </div>
          </template>
        </UPopover>
      </template>
      <NuxtLink
        v-else
        class="btn sm primary"
        to="/login"
      >
        登入
      </NuxtLink>
    </div>
  </header>
</template>
