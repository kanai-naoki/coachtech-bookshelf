<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を更新できるか判定する（登録者本人のみ）。
     *
     * @param  User  $user  操作ユーザー
     * @param  Book  $book  対象の書籍
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるか判定する（登録者本人のみ）。
     *
     * @param  User  $user  操作ユーザー
     * @param  Book  $book  対象の書籍
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
