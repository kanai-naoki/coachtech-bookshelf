<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * APIトークンの発行（ログイン）。
     *
     * @param LoginRequest $request email / password を含む検証済みリクエスト
     * @return JsonResponse access_token と token_type を含むJSON
     * @throws ValidationException 認証情報が正しくない場合
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['認証情報が正しくありません。'],
            ]);
        }

        return response()->json([
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * APIトークンの削除（ログアウト）。現在のリクエストで使用中のトークンのみ削除する。
     *
     * @param Request $request 認証済みユーザーを含むリクエスト
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $request->user()->currentAccessToken();

        $token?->delete();

        return response()->json([
            'message' => 'ログアウトしました。',
        ]);
    }
}
