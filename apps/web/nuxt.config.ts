// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui'
  ],

  devtools: {
    enabled: true
  },

  app: {
    head: {
      htmlAttrs: { lang: 'zh-Hant-TW' },
      titleTemplate: '%s · Pawfit 爪搭',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#ff7a59' }
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&family=Noto+Sans+TC:wght@400;500;700;900&family=Fira+Code:wght@400&display=swap'
        }
      ]
    }
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    // 僅 server 端：SSR 在容器網路內直接呼叫 Laravel
    apiInternalBase: 'http://api:8080/api',
    public: {
      // 瀏覽器端：same-domain 相對路徑，經 Caddy 轉到 Laravel
      apiBase: '/api',
      siteUrl: 'http://localhost:8080',
      // 本機沒有 Google 憑證時，登入頁顯示 email 直接登入（對應 API 的 FEATURE_DEV_LOGIN）
      devLogin: false,
      // 首頁「看示範分享頁」連到的 slug（DemoSeeder 建立）
      demoSlug: ''
    }
  },

  compatibilityDate: '2026-06-30',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  }
})
