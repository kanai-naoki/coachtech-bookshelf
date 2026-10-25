<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

/**
 * ISBN検索（Google Books API連携）のリクエスト。ISBNはルートパラメータで受け取る。
 */
class IsbnSearchRequest extends FormRequest
{
    /**
     * リクエストの実行が認可されているかを判定（認証はミドルウェアで担保）。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーション対象にルートパラメータのISBNを含める。
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return ['isbn' => $this->route('isbn')];
    }

    /**
     * バリデーションルールの定義（13桁）。
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'string', 'size:13'],
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
            'isbn.required' => 'ISBNは必須です。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
        ];
    }

    /**
     * 検証失敗時は 400 Bad Request のJSONを返す。
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], Response::HTTP_BAD_REQUEST));
    }
}
