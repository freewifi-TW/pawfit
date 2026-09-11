<script setup lang="ts">
const { t } = useI18n()
const { me, isAdmin, logout } = useAuth()
const menuOpen = ref(false)
</script>

<template>
  <header class="top">
    <div class="in">
      <PawLogo />
      <nav>
        <NuxtLink to="/">
          {{ t('common.nav.home') }}
        </NuxtLink>
        <template v-if="me?.is_onboarded">
          <NuxtLink to="/dashboard">
            {{ t('common.nav.dashboard') }}
          </NuxtLink>
          <NuxtLink :to="`/u/${me.pawfit_id}`">
            {{ t('common.nav.profile') }}
          </NuxtLink>
          <NuxtLink to="/settings">
            {{ t('common.nav.settings') }}
          </NuxtLink>
          <NuxtLink
            v-if="isAdmin"
            to="/admin"
          >
            {{ t('common.nav.admin') }}
          </NuxtLink>
        </template>
      </nav>
      <span class="sp" />
      <LocaleSwitcher />
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
            <span>{{ me.pawfit_id ? `@${me.pawfit_id}` : (me.display_name || t('common.nav.noId')) }}</span>
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
              >{{ t('common.nav.dashboard') }}</NuxtLink>
              <NuxtLink
                v-if="me.is_onboarded"
                class="btn ghost"
                style="justify-content:flex-start"
                :to="`/u/${me.pawfit_id}`"
                @click="menuOpen = false"
              >{{ t('common.nav.profile') }}</NuxtLink>
              <NuxtLink
                v-else
                class="btn ghost"
                style="justify-content:flex-start"
                to="/onboarding"
                @click="menuOpen = false"
              >{{ t('common.nav.finishSetup') }}</NuxtLink>
              <NuxtLink
                class="btn ghost"
                style="justify-content:flex-start"
                to="/settings"
                @click="menuOpen = false"
              >{{ t('common.nav.settings') }}</NuxtLink>
              <NuxtLink
                v-if="isAdmin"
                class="btn ghost"
                style="justify-content:flex-start"
                to="/admin"
                @click="menuOpen = false"
              >{{ t('common.nav.adminPanel') }}</NuxtLink>
              <button
                class="btn ghost"
                style="justify-content:flex-start;color:var(--danger)"
                type="button"
                @click="menuOpen = false; logout()"
              >
                {{ t('common.nav.logout') }}
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
        {{ t('common.nav.login') }}
      </NuxtLink>
    </div>
  </header>
</template>
