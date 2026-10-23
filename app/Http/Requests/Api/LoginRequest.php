<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * ログインは未認証ユーザーが行うため常に許可する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールの定義。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required'],
        ];
    }

    /**
     * カスタムエラーメッセージの定義。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'メールアドレスは必須です。',
            'email.email' => 'メールアドレスの形式で入力してください。',
            'password.required' => 'パスワードは必須です。',
        ];
    }
}
