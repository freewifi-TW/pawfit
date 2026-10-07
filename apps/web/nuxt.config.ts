// https://nuxt.com/docs/api/configuration/nuxt-config

/**
 * 多語系：
 * - 支援的語言在 SUPPORTED_LOCALES；新增語言＝加一個項目 + 在 i18n/locales/<code>/ 補齊各 namespace 的 JSON
 * - 每個 namespace 一個檔案（common、auth、fursona…），檔案最上層 key 就是 namespace 名稱
 * - 路徑不加語言前綴（no_prefix），語言記在 cookie；登入者另存在 API 的 user.locale（見 useLocale）
 * - 後端 Laravel 依 Accept-Language 回對應語言的訊息（useApi 會帶）
 */
export const SUPPORTED_LOCALES = [
  { code: 'zh-TW', language: 'zh-Hant-TW', name: '繁體中文', dir: 'ltr' },
  { code: 'en', language: 'en', name: 'English', dir: 'ltr' }
] as const

export const DEFAULT_LOCALE = 'zh-TW'

const NAMESPACES = ['common', 'enums', 'home', 'auth', 'dashboard', 'fursona', 'media', 'share', 'profile', 'settings', 'admin', 'legal', 'embed', 'commission', 'friends', 'feed', 'headsticker']

export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@nuxtjs/i18n'
  ],

  devtools: {
    enabled: true
  },

  app: {
    head: {
      // lang 由 i18n（useLocaleHead）依目前語言設定
      titleTemplate: '%s · Pawfit',
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
  },

  i18n: {
    locales: SUPPORTED_LOCALES.map(l => ({
      ...l,
      files: NAMESPACES.map(ns => `${l.code}/${ns}.json`)
    })),
    defaultLocale: DEFAULT_LOCALE,
    strategy: 'no_prefix',
    langDir: 'locales',
    vueI18n: './i18n.config.ts',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'pawfit_locale',
      cookieSecure: false,
      alwaysRedirect: false,
      fallbackLocale: DEFAULT_LOCALE,
      redirectOn: 'root'
    }
  }
})
