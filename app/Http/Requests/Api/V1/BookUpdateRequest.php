<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * 書籍更新リクエスト。登録時と同じルール・メッセージで、ISBN の重複チェックのみ自身を除外する。
 */
class BookUpdateRequest extends BookStoreRequest
{
    /**
     * バリデーションルールの定義。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $book = $this->route('book');

        return array_merge(parent::rules(), [
            'isbn' => [
                'required',
                'string',
                'size:13',
                Rule::unique('books', 'isbn')->ignore(is_object($book) ? $book->id : $book),
            ],
        ]);
    }
}
