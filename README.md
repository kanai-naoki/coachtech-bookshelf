# COACHTECH ブックシェルフ

## 概要

本プロジェクトは、書籍の登録・管理およびレビュー投稿・閲覧を行うWebアプリケーション「BookShelf」です。
Traditional Web (SSR) 構成をベースとし、ユーザーによる書籍情報の登録・編集および検索、レビュー評価、お気に入り登録、ジャンルの登録・編集、平均評価に基づくランキング、読書計画、リマインダー通知、マイ読書レポート、公開用APIなどを提供しています。

## 実装機能一覧

【ユーザー認証機能】

- **会員登録・ログイン・ログアウト** (`/register`, `/login`, `/logout`)
    - 会員登録（氏名・メールアドレス・パスワード）
    - ログイン・ログアウト処理

【書籍管理・閲覧機能】

- **書籍一覧（トップ）画面** (`/` または `/books`)
    - 検索機能：キーワード（titleまたはauthorの部分一致）、ジャンル
    - ソート機能（latest:登録が新しい順（デフォルト）、oldest:登録が古い順、title:タイトル順、rating:評価の高い順）
    - 登録済み書籍の一覧表示（最新順または検索条件を維持したまま10件ごとのページネーション）
- **書籍詳細画面** (`/books/{book}`)
    - 書籍の基本情報（タイトル・著者・ISBN・ジャンル等）およびレビュー・お気に入り・いいね機能の表示
- **書籍登録画面** (`/books/create`) ※認証必須
    - 書籍情報（タイトル、著者、ISBN、出版日、概要、画像URL）の新規登録
    - ISBN検索機能（GoogleAPIからISBN番号情報を取得）
    - ジャンルの選択（複数選択可能なチェックボックス）
- **書籍編集画面** (`/books/{book}/edit`) ※認証＋認可必須（投稿者のみ）
    - 自身が登録した書籍情報の編集および削除

【レビュー・評価機能】

- **レビュー投稿・編集** (`/books/{book}`, `/reviews/{review}/edit`) ※認証必須
    - 1ユーザー1書籍につき1件のレビュー投稿（星評価・コメント）
    - 自身が投稿したレビューの編集・削除
- **レビューいいね機能** (`/books/{book}`) ※認証必須
    - レビューに対する「いいね」の登録および解除

【お気に入り機能】

- **お気に入り登録・一覧** (`/favorites`) ※認証必須
    - 書籍のお気に入り登録および解除
    - お気に入り登録した書籍の一覧表示（10件ごとのページネーション）

【ジャンル管理機能】

- **ジャンル一覧・詳細** (`/genres`, `/genres/{genre}`) ※認証必須
    - 各ジャンルの書籍数表示
    - ジャンルに紐づく書籍の一覧表示（10件ごとのページネーション）
- **ジャンル登録・編集** (`/genres/create`, `/genres/{genre}/edit`) ※認証必須
    - ジャンルの新規追加・編集・削除

【ランキング機能】

- **ランキング画面** (`/ranking`)
    - レビュー平均評価が高い上位10件の書籍を表示

【読書計画機能】

- **読書計画一覧（トップ）画面** (`/reading-plans`)
    - 状態別絞り込み（進行中、読了、期限切れ）
- **読書計画登録画面** (`/reading-plans/create`) ※認証必須
    - 読書計画（書籍、期日）の新規登録
- **読書計画編集画面** (`reading-plans/{plan}/edit`) ※認証＋認可必須（投稿者のみ）
    - 自身が登録した書籍情報の編集および削除

【通知機能】

- **通知一覧画面** (`/notifications`)※認証必須
    - 各通知に既読化操作が可能
- **日時パッチ処理** (Schedule 経由)
    - 期日3日前、当日にリマインダー通知を発火する
    - 期日を経過した計画のステータスを自動的に変更

【マイ読書レポート機能】

- **マイ読書レポート一覧画面** (`/reports`)※認証必須
    - 基本サマリー: 総レビュー数、読了冊数（ユニーク書籍数）、平均評価点
    - 評価分布: 1〜5星ごとの件数を横バーで表示
    - 高評価書籍TOP5: 4星以上の書籍を評価の高い順に最大5件表示（書籍詳細リンク付き）
    - ジャンル別評価傾向TOP5: ジャンルごとの平均評価と件数を高い順に最大5件表示（ジャンル詳細リンク付き）

【公開API機能】

- **書籍データCRUD API** (`/api/v1/books`)
    - 書籍一覧・詳細データの取得（JSON形式）
    - 書籍データの新規登録・更新・削除（※認証（Sanctum認証トークン）・認可必須）

---

## 開発環境URL

- **Webアプリケーション**: [http://localhost](http://localhost)
- **phpMyAdmin**: [http://localhost:8080](http://localhost:8080)

---

## 使用技術

- **OS**: Linux (Docker環境)
- **言語 / 構成**: PHP 8.5 / Laravel 10.x
- **データベース**: MySQL 8.4
- **Webサーバー**: Nginx
- **フロントエンド**: Vite, Tailwind CSS ^3.4.0, Alpine.js
- **開発環境・管理ツール**: Docker, Laravel Sail, phpMyAdmin

---

## ER図

![alt text](<スクリーンショット 2026-10-26 022238.png>)

---

## APIエンドポイント一覧

認証不要の REST API エンドポイントを提供しています。

| HTTPメソッド | URI                     | 説明                                   |
| :----------- | :---------------------- | :------------------------------------- |
| **GET**      | `/api/v1/books`         | 書籍一覧（検索・ページネーション付き） |
| **GET**      | `/api/v1/books/{books}` | 書籍詳細                               |
| **POST**     | `/api/v1/books`         | 書籍新規登録                           |
| **PUT**      | `/api/v1/books/{books}` | 書籍更新                               |
| **DELETE**   | `/api/v1/books/{books}` | 書籍削除                               |

---

## 環境構築手順

### 1. リポジトリのクローン

本プロジェクトを GitHub からローカル環境へクローンします。

```bash
git clone git@github.com:kanai-naoki/coachtech-bookshelf.git
cd coachtech-bookshelf
```

_(※ HTTPS経由でクローンする場合は `git clone https://github.com/kanai-naoki/coachtech-bookshelf.git`)_

### 2. 環境変数の設定 (`.env`)

`.env.example` をコピーして `.env` を作成します。

```bash
cp .env.example .env
```

`.env` 内のデータベース設定が以下の内容になっているか確認します：

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

### 3. Composer パッケージのインストール

Docker イメージを経由して依存関係をインストールします。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer install
```

### 4. Docker (Laravel Sail) の起動とエイリアス設定

Docker コンテナをバックグラウンドで起動します。

```bash
./vendor/bin/sail up -d
```

_(エイリアス設定)_

```bash
echo "alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'" >> ~/.bashrc
source ~/.bashrc
```

_※ 初回起動時や他環境での実行時にストレージの権限エラーが発生する場合は、以下のコマンドを実行してください。_

```bash
sail exec laravel.test chmod -R 777 storage bootstrap/cache
```

### 5. アプリケーションキーの生成

```bash
sail artisan key:generate
```

### 6. フロントエンドパッケージのインストールとビルド

```bash
sail npm install
sail npm run build
```

### 7. データベースマイグレーションおよび初期データの投入

```bash
sail artisan migrate --seed
```

### 8. テストの実行

以下のコマンドで自動テスト（PHPUnit / Pest）を実行します。

```bash
sail test
```

---

## 作成者

家内 直紀
