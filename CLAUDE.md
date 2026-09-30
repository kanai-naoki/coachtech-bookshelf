# CLAUDE.md

Bookshelf App（書籍管理・レビュー共有アプリ）。開発・リファクタリング時の指針をまとめる。

## 技術スタック

- Laravel 10 / PHP ^8.1（Sail ランタイムは `vendor/laravel/sail/runtimes/8.5`）
- MySQL 8.4（Docker Sail、`compose.yaml`。DBホストは `mysql`）
- 認証: Laravel Fortify（`app/Actions/Fortify`, `FortifyServiceProvider`）＋ Sanctum（導入済み・API側では未使用）
- フロント: Blade ＋ Tailwind CSS 3 ＋ Vite 5（`resources/views`。`bookshelf/` 配下はビュー等の別コピーで、本体ではない）
- コード整形: Laravel Pint / テスト: PHPUnit 10

## コマンド

コマンドは原則 Sail 経由で実行する。

```bash
./vendor/bin/sail up -d                      # 起動
./vendor/bin/sail down                       # 停止
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan test               # 全テスト（テストDBは "testing"）
./vendor/bin/sail artisan test --filter=XxxTest
./vendor/bin/sail composer install
./vendor/bin/sail bin pint                   # コード整形（--test で差分確認のみ）
./vendor/bin/sail npm run dev                # Vite 開発サーバ
./vendor/bin/sail npm run build
```

- API 動作確認用のリクエスト集: `test.http`
- ER 図: `schema.drawio`

## ディレクトリ構成

```
app/
  Actions/Fortify/          ユーザー登録・パスワード更新等
  Http/Controllers/         Web用コントローラ（Book/Genre/Review/ReviewLike/Favorite/Ranking）
  Http/Controllers/Api/V1/  公開API（BookController）
  Http/Requests/            FormRequest（Web用: Book/Genre/Review、API用: Api/V1/Book{Index,Store,Update}Request）
  Http/Resources/Api/V1/    BookResource
  Models/                   User, Book, Genre, Review
  Policies/                 BookPolicy, ReviewPolicy（作成者本人のみ update/delete 可）
database/migrations/        2026_09_02_* が本アプリのテーブル
database/seeders/           User → Genre → Book → Review → Favorite → ReviewLike
resources/views/            books, genres, reviews, favorites, ranking, auth, components, layouts
routes/web.php, api.php     ルート定義
```

## ドメインとDB設計

| テーブル | 主な制約 |
|---|---|
| users | Fortify標準（2FAカラム含む） |
| genres | `name` unique、`user_id` FK |
| books | `isbn` unique（13桁）、`published_date` 必須、`description`/`image_url` nullable、`user_id` FK |
| book_genre | 中間テーブル。`unique(book_id, genre_id)` |
| reviews | `rating` unsignedTinyInteger、`unique(user_id, book_id)`（1ユーザー1書籍1レビュー） |
| favorites | 中間テーブル。`unique(user_id, book_id)` |
| review_likes | 中間テーブル。`unique(user_id, review_id)` |

- すべての `user_id` / `book_id` 等の FK は `cascadeOnDelete`。
- リレーション: Book belongsTo User / hasMany Review / belongsToMany Genre（`book_genre`）/ belongsToMany User（`favorites`, `favoriteUsers`）。Review は `likedByUsers`（`review_likes`）。User は `favoriteBooks`, `likedReviews` を持つ。
- **バリデーションはDB制約と必ず一致させる**（unique・長さ・nullable・FK 存在チェック `exists:`）。カラムを変更したら該当 FormRequest とその `messages()` も更新する。

## ルーティング概要

- `web.php` の `auth` グループ: genres（resource）、books（index/show 以外）、`books.reviews`（shallow, store/edit/update/destroy）、`reviews.like`、`favorites.index`、`favorites.toggle`
- ゲスト可: `/` および `books.index/show`、`/ranking`（レビュー平均評価 TOP10）
- `api.php`: `/api/v1/books` の `apiResource`（一覧・検索・ジャンル絞り込み・ページネーション、CRUD）。現状は認証ミドルウェアなし。

## 開発方針

1. **Laravel標準規約に従う**: リソースコントローラ、ルートモデルバインディング、命名規約（複数形テーブル、単数形モデル、`{model}_id` FK）、Policy による認可。
2. **厳格な型宣言と PHPDoc**: 引数・戻り値の型を必ず書く。配列戻り値は `@return array<string, ...>` のように内容を PHPDoc で示す。リレーションメソッドには `: BelongsTo` / `: HasMany` / `: BelongsToMany` の戻り値型と `@return` ジェネリクスを付ける（現状の Models は未対応のため、触る際に補う）。
3. **Collection / Eloquent を活用**: ループでの手作業集計やN+1を避け、`with()` / `withAvg()` / `withCount()` / `whereHas()` / `sync()` / `toggle()`、Collection メソッド（`map`, `pluck`, `groupBy` 等）で表現する。
4. **バリデーションは FormRequest に集約**: コントローラ内で `$request->validate()` を書かない。認可は Policy（`authorize()` は Policy 呼び出し or 明示的に true）。日本語メッセージは `messages()` に定義（既存の `BookRequest` に倣う）。
5. **コントローラは薄く**: 複数モデルにまたがる処理・検索条件の組み立ては、肥大化したら Model スコープ（`scopeXxx`）や Action/Service クラスへ切り出す。複数テーブル更新はトランザクションを検討する。
6. コメント・メッセージは日本語（既存コードに合わせる）。

## 既知の注意点（リファクタリング候補）

- Web の `BookRequest` はジャンル入力キーが `genres`、API の `BookStoreRequest` / `BookUpdateRequest` は `genre_ids`。キー名が不統一。
- API の `BookController` の `index` の検索ロジックは Controller にインライン記述されている（Model スコープ化の余地）。
- API は認証なしで書き込み系（POST/PUT/DELETE）が可能。認可を入れる場合は Sanctum ＋ Policy を検討。
- 各 Model のリレーションメソッドに戻り値型がない。
- テストは `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` のみ（実質未整備）。`RefreshDatabase` ＋ Factory（現状は `UserFactory` のみ）で拡充する。
- 現在のブランチ運用: `main` へ PR マージ（`feature/*`、`test/*`）。
