<?php

// 委託需求單文字模板（FR-6.2）。兩份語言檔（zh_TW / en）的 key 結構必須完全一致。
// 由 App\Services\CommissionBrief 組合；色票 hex 與 credit 為原始資料，模板不得改寫。

return [
    'title' => '【委託需求單】:name',
    'title_species' => '【委託需求單】:name（:species）',
    'generated_by' => '由 @:pawfit_id 透過 Pawfit 爪搭產生 · :date',
    'kit_link' => '需求單連結（含參考圖原尺寸）：:url',
    'share_link' => '角色完整設定：:url',

    'section_character' => '■ 角色',
    'name' => '名稱：:name',
    'species' => '物種：:species',
    'tags' => '特徵標籤：:tags',
    'bio' => '簡介：',

    'section_palette' => '■ 色票（請以色碼為準）',
    'palette_item' => ':index. :name :hex',
    'palette_item_note' => ':index. :name :hex（:note）',
    'palette_unnamed' => '色票 :index',
    'palette_empty' => '（尚未設定色票）',

    'section_references' => '■ 參考圖（共 :count 張，原尺寸請見需求單連結）',
    'reference_item' => ':index. :caption',
    'reference_item_credit' => ':index. :caption — 繪師：:credit',
    'reference_untitled' => '參考圖 :index',
    'reference_nsfw' => '（NSFW）',

    'section_request' => '■ 本次委託',
    'request_fields' => [
        'composition' => '構圖／姿勢',
        'scene' => '情境／背景',
        'size' => '尺寸／規格',
        'usage' => '用途',
        'budget' => '預算',
        'deadline' => '截稿',
        'notes' => '補充',
    ],
    'request_empty' => '（請與委託者確認細節）',

    'footer' => '— 本需求單由 Pawfit 爪搭依角色設定自動整理，色票與繪師標記為原始資料；未使用任何 AI 生圖。',
];
