<?php

return [
    'accepted' => ':attribute 必須接受。',
    'confirmed' => ':attribute 與確認欄位不一致。',
    'email' => ':attribute 必須是有效的電子郵件地址。',
    'integer' => ':attribute 必須是整數。',
    'max' => [
        'numeric' => ':attribute 不得大於 :max。',
        'string' => ':attribute 不得超過 :max 個字元。',
    ],
    'min' => [
        'numeric' => ':attribute 不得小於 :min。',
        'string' => ':attribute 至少需要 :min 個字元。',
    ],
    'numeric' => ':attribute 必須為數字。',
    'required' => ':attribute 為必填欄位。',
    'string' => ':attribute 必須是字串。',
    'unique' => ':attribute 已經被使用。',

    'attributes' => [
        'name' => '姓名',
        'email' => '電子郵件',
        'password' => '密碼',
        'password_confirmation' => '確認密碼',
        'role' => '角色',
    ],
];
