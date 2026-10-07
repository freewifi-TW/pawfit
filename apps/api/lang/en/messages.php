<?php

// Pawfit 業務訊息（統一由 __("messages.<群組>.<鍵>") 取用）。鍵名依 controller / service 分群。
// 兩份語言檔（zh_TW / en）的 key 結構必須完全一致。

return [
    'me' => [
        'pawfit_id_format' => 'Pawfit ID must be 3 to 20 characters using only letters, numbers, and underscores.',
        'pawfit_id_reserved' => 'This Pawfit ID is reserved. Please choose another one.',
        'pawfit_id_locked' => 'Pawfit ID cannot be changed at this time.',
        'pawfit_id_taken' => 'This Pawfit ID is already taken.',
        'nsfw_pref_requires_adult' => 'Please confirm you are 18 or older before changing how adult content is displayed.',
        'avatar_not_found' => 'This image could not be found or is still processing.',
    ],

    'auth' => [
        'google_not_configured' => 'Google sign-in is not configured yet.',
        'banned' => 'This account has been suspended.',
        'onboarding_required' => 'Please set your Pawfit ID and accept the Terms of Service first.',
    ],

    'fursona' => [
        'not_found' => 'Fursona not found.',
        'media_not_owned' => 'Some of these images do not belong to this fursona.',
        'palette_hex_format' => 'Color codes must be in #RRGGBB format.',
        'avatar_not_in_gallery' => 'The avatar must be a fully processed image from this fursona\'s gallery.',
    ],

    'media' => [
        'content_type_not_allowed' => 'Only jpg, png, and webp files are accepted.',
        'file_too_large' => 'Each file must be :max MB or smaller.',
        'daily_limit_reached' => 'You have reached today\'s upload limit. Please try again tomorrow.',
        'storage_full' => 'Not enough storage space.',
        'kind_required' => 'Please choose a category (2D / 3D / Physical).',
        'is_nsfw_required' => 'Please set a content rating (SFW / NSFW).',
        'invalid_storage_key' => 'Invalid file location.',
        'already_confirmed' => 'This file has already been confirmed.',
        'upload_not_found' => 'The uploaded file could not be found. Please upload it again.',
        'file_exceeds_limit' => 'The file exceeds :max MB.',
    ],

    'commission' => [
        'media_required' => 'Pick at least one reference image.',
        'too_many_media' => 'At most :max reference images.',
        'fursona_not_owned' => 'Fursona not found.',
        'media_not_owned' => 'Includes images that do not belong to this fursona.',
        'media_not_active' => 'Some images are still processing or have been removed. Please deselect them.',
        'revoked' => 'This brief has been disabled.',
    ],

    'report' => [
        'target_not_found' => 'Report target not found.',
        'untitled_media' => 'Untitled image',
        'no_pawfit_id' => 'not set',
    ],

    'admin' => [
        'removed_by_staff' => 'Removed by moderators',
        'purged_red_line' => 'Prohibited content; object deleted',
        'object_deleted' => 'R2 object deleted',
        'cannot_restore_purged' => 'The object has been deleted and cannot be restored.',
        'marked_nsfw' => 'Marked as NSFW',
        'cannot_ban_admin' => 'Administrators cannot be suspended.',
        'unknown_action' => 'Unknown action.',
    ],
];
