// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui'
  ],

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  app: {
    head: {
      htmlAttrs: { lang: 'zh-Hant-TW' },
      titleTemplate: '%s · Pawfit 爪搭',
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&family=Noto+Sans+TC:wght@400;500;700;900&family=Fira+Code:wght@400&display=swap'
        }
      ]
    }
  },

  runtimeConfig: {
    // 僅 server 端：SSR 在容器網路內直接呼叫 Laravel
    apiInternalBase: 'http://api:8080/api',
    public: {
      // 瀏覽器端：same-domain 相對路徑，經 Caddy 轉到 Laravel
      apiBase: '/api',
      siteUrl: 'http://localhost:8080'
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
