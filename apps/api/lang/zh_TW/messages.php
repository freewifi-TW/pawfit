<?php

// Pawfit 業務訊息（統一由 __("messages.<群組>.<鍵>") 取用）。鍵名依 controller / service 分群。
// 兩份語言檔（zh_TW / en）的 key 結構必須完全一致。

return [
    'me' => [
        'pawfit_id_format' => 'Pawfit ID 只能用 3 到 20 個英數字與底線。',
        'pawfit_id_reserved' => '這個 Pawfit ID 是保留字，換一個吧。',
        'pawfit_id_locked' => 'Pawfit ID 目前不開放更改。',
        'pawfit_id_taken' => '這個 Pawfit ID 已經有人用了。',
        'nsfw_pref_requires_adult' => '請先完成 18 歲聲明，才能切換成人內容的顯示方式。',
        'avatar_not_found' => '找不到這張圖，或它還在處理中。',
    ],

    'auth' => [
        'google_not_configured' => 'Google 登入尚未設定。',
        'banned' => '此帳號已被停權。',
        'onboarding_required' => '請先完成 Pawfit ID 設定與條款同意。',
    ],

    'fursona' => [
        'not_found' => '找不到這隻獸設。',
        'media_not_owned' => '包含不屬於這隻獸設的圖片。',
        'palette_hex_format' => '色碼格式需為 #RRGGBB。',
        'avatar_not_in_gallery' => '頭像必須是這隻獸設圖庫中已處理完成的圖片。',
    ],

    'media' => [
        'content_type_not_allowed' => '只接受 jpg、png、webp。',
        'file_too_large' => '單檔上限 :max MB。',
        'daily_limit_reached' => '今日上傳張數已達上限，明天再試。',
        'storage_full' => '儲存空間不足。',
        'kind_required' => '請選擇分類（2D／3D／實體）。',
        'is_nsfw_required' => '請標記內容分級（SFW／NSFW）。',
        'invalid_storage_key' => '無效的檔案位置。',
        'already_confirmed' => '這個檔案已經確認過了。',
        'upload_not_found' => '找不到上傳的檔案，請重新上傳。',
        'file_exceeds_limit' => '檔案超過 :max MB。',
    ],

    'friends' => [
        'user_not_found' => '找不到這個 Pawfit ID。',
        'self' => '不能對自己做這個操作。',
        'already_friends' => '你們已經是好友了。',
        'already_requested' => '邀請已送出，等待對方回應。',
        'cannot_accept_own' => '這是你送出的邀請，要等對方接受。',
        'use_remove' => '已是好友，請改用解除好友。',
    ],

    'posts' => [
        'media_required' => '請至少選一張圖。',
        'too_many_media' => '一篇貼文最多 :max 張圖。',
        'media_locked' => '貼文的圖片組成不能事後變更，請重新發文。',
        'is_nsfw_required' => '請標記貼文分級（SFW／NSFW）。',
        'fursona_not_owned' => '找不到這隻獸設。',
        'media_not_owned' => '只能選自己圖庫的圖。',
        'media_not_active' => '有圖片還在處理中或已下架。',
        'nsfw_locked' => '來源圖或獸設為 NSFW，貼文分級鎖定為 NSFW。',
        'suppressed_note' => '多筆檢舉，暫時降低能見度，待站方審核',
    ],

    'commission' => [
        'media_required' => '請至少選一張參考圖。',
        'too_many_media' => '參考圖最多 :max 張。',
        'fursona_not_owned' => '找不到這隻獸設。',
        'media_not_owned' => '包含不屬於這隻獸設的圖片。',
        'media_not_active' => '有圖片還在處理中或已下架，請先移除。',
        'revoked' => '這份需求單已停用。',
    ],

    'report' => [
        'target_not_found' => '找不到檢舉目標。',
        'untitled_media' => '未命名圖片',
        'untitled_post' => '（無文字）',
        'no_pawfit_id' => '未設定',
    ],

    'admin' => [
        'removed_by_staff' => '由站方下架',
        'purged_red_line' => '紅線內容，物件已刪除',
        'object_deleted' => 'R2 物件已刪除',
        'cannot_restore_purged' => '物件已被刪除，無法還原。',
        'marked_nsfw' => '改標為 NSFW',
        'cannot_ban_admin' => '不能停權管理員。',
        'unknown_action' => '未知的操作。',
    ],
];
