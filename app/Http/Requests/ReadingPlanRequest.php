<?php

namespace App\Http\Requests;

use App\Enums\ReadingPlanStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 読書計画の登録・更新リクエスト。登録時は同一ユーザー・同一書籍の「進行中」の重複のみ禁止する（読了済み・期限切れかどうかはバリデーション側のstatus絞り込みで判定）。
 */
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'book_id' => $this->isMethod('post')
                ? [
                    'required',
                    'integer',
                    'exists:books,id',
                    Rule::unique('reading_plans', 'book_id')
                        ->where(fn (Builder $query): Builder => $query
                            ->where('user_id', $this->user()->id)
                            ->where('status', ReadingPlanStatus::InProgress->value)),
                ]
                : [
                    'sometimes',
                    'integer',
                    'exists:books,id',
                ],

            'target_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
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
            'book_id.required' => '書籍を選択してください。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.unique' => 'この書籍は既に進行中の読書計画が存在します。',
            'target_date.required' => '期日は必須です。',
            'target_date.date' => '期日は有効な日付形式で入力してください。',
            'target_date.after_or_equal' => '期日は今日以降の日付を指定してください。',
        ];
    }
}
