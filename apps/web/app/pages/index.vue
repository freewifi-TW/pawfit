<script setup lang="ts">
const { t } = useI18n()
const { me } = useAuth()
const config = useRuntimeConfig()
const route = useRoute()
const notify = useNotify()

useSeoMeta({
  title: () => t('home.seo.title'),
  description: () => t('home.seo.description')
})

const demoSlug = config.public.demoSlug
const startTo = computed(() => (me.value ? (me.value.is_onboarded ? '/dashboard' : '/onboarding') : '/login'))

onMounted(() => {
  if (route.query.banned) notify.err(t('home.banned.title'), t('home.banned.hint'))
})
</script>

<template>
  <section class="wrap">
    <div class="hero">
      <div>
        <i18n-t
          keypath="home.hero.title"
          tag="h1"
          class="disp"
        >
          <template #br>
            <br>
          </template>
          <template #mark>
            <mark>{{ t('home.hero.titleMark') }}</mark>
          </template>
        </i18n-t>
        <p>{{ t('home.hero.lead') }}</p>
        <div class="cta">
          <NuxtLink
            class="btn primary lg"
            :to="startTo"
          >
            {{ me ? t('home.hero.ctaEnter') : t('home.hero.ctaStart') }}
          </NuxtLink>
          <NuxtLink
            v-if="demoSlug"
            class="btn lg"
            :to="`/s/${demoSlug}`"
          >
            {{ t('home.hero.ctaDemo') }}
          </NuxtLink>
        </div>
      </div>
      <div class="peek">
        <span class="sticker">{{ t('home.demo.sticker') }}</span>
        <div class="card">
          <div class="row">
            <BlobAvatar
              :size="56"
              :border="3"
              alt=""
            />
            <div>
              <b
                class="disp"
                style="font-size:22px"
              >{{ t('home.demo.name') }}</b>
              <div
                class="sub"
                style="font-size:13px"
              >
                {{ t('home.demo.meta') }}
              </div>
            </div>
          </div>
          <div class="tags">
            <span
              class="tag mint"
              style="--r:-2deg"
            >{{ t('home.demo.tagCanine') }}</span><span
              class="tag"
              style="--r:1.5deg"
            >{{ t('home.demo.tagFox') }}</span><span
              class="tag butter"
              style="--r:-1deg"
            >{{ t('home.demo.tagBuild') }}</span>
          </div>
          <div class="swatch-strip">
            <i style="background:#D9642A" /><i style="background:#F4E7D3" /><i style="background:#2B2420" /><i style="background:#7FB7A3" /><i style="background:#4A2E2A" /><i style="background:#F1EEE8" />
          </div>
          <div
            class="row"
            style="font-size:12px;color:var(--ink-3)"
          >
            <span>{{ t('home.demo.refCount') }}</span><span>·</span><span>{{ t('home.demo.creditCount') }}</span><span class="sp" /><span class="mono">pawfit.app/s/demo…</span>
          </div>
        </div>
      </div>
    </div>

    <div class="feats">
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--mint)"
        >
          🗂️
        </div>
        <h3 class="disp">
          {{ t('home.features.collect.title') }}
        </h3>
        <p>{{ t('home.features.collect.body') }}</p>
      </div>
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--butter)"
        >
          🎨
        </div>
        <h3 class="disp">
          {{ t('home.features.palette.title') }}
        </h3>
        <p>{{ t('home.features.palette.body') }}</p>
      </div>
      <div class="card feat">
        <div
          class="ic"
          style="background:var(--sky)"
        >
          🔗
        </div>
        <h3 class="disp">
          {{ t('home.features.share.title') }}
        </h3>
        <p>{{ t('home.features.share.body') }}</p>
      </div>
    </div>

    <div class="card notice">
      <span style="font-size:24px">🔞</span>
      <div><b>{{ t('home.notice.title') }}</b> {{ t('home.notice.body') }}</div>
    </div>
  </section>
</template>
