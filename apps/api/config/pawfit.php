<?php

/*
 |------------------------------------------------------------------
 | Pawfit 專案設定
 |------------------------------------------------------------------
 | Phase 1 的業務常數與 feature flag。flag 一律用環境變數控制，
 | 程式碼可先部署、功能再啟用（SASD §0.1 原則 1）。
 */

return [

    // 管理員名單：以 email 維護（逗號分隔），初期只有站方一人
    'admin_emails' => array_values(array_filter(array_map(
        fn (string $e) => strtolower(trim($e)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

    'features' => [
        // 本機沒有 Google OAuth 憑證時，允許用 email 直接登入（僅 local 環境生效）
        'dev_login' => (bool) env('FEATURE_DEV_LOGIN', false),
        // 後續 phase 的 flag 預留
        'feed' => (bool) env('FEATURE_FEED', false),
        'wardrobe' => (bool) env('FEATURE_WARDROBE', false),
    ],

    'quota' => [
        // 單用戶原檔總容量（bytes），預設 2 GB
        'storage_bytes' => (int) env('QUOTA_STORAGE_BYTES', 2 * 1024 * 1024 * 1024),
        // 單日上傳張數
        'daily_uploads' => (int) env('QUOTA_DAILY_UPLOADS', 50),
        // 單檔上限（bytes），預設 20 MB
        'max_file_bytes' => (int) env('QUOTA_MAX_FILE_BYTES', 20 * 1024 * 1024),
    ],

    'media' => [
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'display_max_edge' => 2048,
        'thumb_max_edge' => 512,
        // 私有內容簽名 URL 的有效時間（分鐘）
        'signed_url_ttl' => (int) env('MEDIA_SIGNED_URL_TTL', 15),
        // presigned PUT 有效時間（分鐘）
        'presign_ttl' => 10,
        'watermark_font' => resource_path('fonts/Baloo2-Variable.ttf'),
    ],

    'fursona' => [
        'max_palette' => 24,
        'max_tags' => 30,
    ],

    // 前端網址（分享連結、OAuth 完成後轉址用）
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:8080')), '/'),

    // 多語系（BCP 47）。新增語言：這裡加一個 code，並補 lang/<code 底線版>/ 與前端 i18n/locales/<code>/。
    // 前端清單在 apps/web/nuxt.config.ts 的 SUPPORTED_LOCALES，兩邊要一致。
    'locales' => [
        'supported' => ['zh-TW', 'en'],
        'default' => 'zh-TW',
    ],
];
