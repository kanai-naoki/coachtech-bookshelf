<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * リソースを配列へ変換
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->description,
            'image_url' => $this->image_url,
            // 日時フォーマット: Y-m-d H:i:s
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // ジャンル一覧（関連データが含まれている場合）
            'genres' => $this->whenLoaded('genres', function () {
                return $this->genres->map(function ($genre) {
                    return [
                        'id' => $genre->id,
                        'name' => $genre->name,
                    ];
                });
            }),

            // 集計データ（withAvg / withCount で読み込まれている場合）
            'average_rating' => isset($this->reviews_avg_rating) ? round((float) $this->reviews_avg_rating, 1) : 0,
            'review_count' => $this->reviews_count ?? 0,
        ];
    }
}