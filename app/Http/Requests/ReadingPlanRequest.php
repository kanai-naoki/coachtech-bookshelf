<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class ReadingPlanRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを実行する権限があるかどうかを判定する
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用するバリデーションルールを取得する
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // 新規作成時のみ book_id を必須とし、更新時は送られてこない/変更不可の場合にも対応できる記述例
        return [
            'book_id' => $this->isMethod('post')
                ? [
                    'required',
                    'integer',
                    'exists:books,id',
                    Rule::unique('reading_plans', 'book_id')->where(function ($query) {
                        return $query->where('user_id', $this->user()->id);
                    }),
                ]
                : ['sometimes', 'integer', 'exists:books,id'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * バリデーションエラーのカスタムメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'book_id.required' => '書籍は必須です。',
            'book_id.exists' => '選択された書籍が存在しません。',
            'book_id.unique' => 'この書籍の読書計画はすでに登録されています。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }
}