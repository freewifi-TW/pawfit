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
        // Phase 2 M1：好友與封鎖、限好友隱私（SASD Phase 2 FR-B1、FR-B2）
        'friends' => (bool) env('FEATURE_FRIENDS', false),
        // Phase 2 M2–M4：發文、河道、互動、治理強化
        'feed' => (bool) env('FEATURE_FEED', false),
        // M7 委託需求單的 LLM 潤飾（FR-6.7）：純文字模型改寫語氣與翻譯；預設關，開啟前需先接供應商
        'brief_llm' => (bool) env('FEATURE_BRIEF_LLM', false),
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

    // M7 委託需求單（FR-6）
    'commission' => [
        // 每份需求單的參考圖張數上限
        'max_media' => 10,
        // 合成圖文字字型檔（映像已裝 font-noto-cjk）；檔案不存在時退回 media.watermark_font（Baloo2，無中文）
        'font' => env('COMMISSION_FONT', '/usr/share/fonts/noto/NotoSansCJK-Regular.ttc'),
    ],

    // M6 開放與嵌入（FR-7）：公開 JSON API、oEmbed、SVG 色票卡、iframe 卡片
    'embed' => [
        // 公開 API 與嵌入端點的 IP rate limit（每分鐘）
        'rate_per_minute' => (int) env('EMBED_RATE_PER_MINUTE', 60),
        // 回應快取秒數：JSON 300、SVG 600（FR-7.3、FR-7.5）
        'json_max_age' => 300,
        'svg_max_age' => 600,
        // 每頁 media 筆數
        'media_page_size' => 24,
        // iframe 卡片尺寸（oEmbed 與設定頁產生器共用；與 apps/web 的 EMBED_SIZES 一致）
        'sizes' => [
            'sm' => [320, 200],
            'md' => [480, 320],
            'lg' => [640, 420],
        ],
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
