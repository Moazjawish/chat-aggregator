<?php

namespace App\Http\Controllers;

use App\Models\AIModel;
use App\Services\AI\AIService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function chat(Request $request, AIService $aiService)
    {
        $request->validate([
            'model_id' => ['required', 'integer', 'exists:models,id'],
            'message' => ['required', 'string'],
        ]);

        $user = $request->user();

        $model = AIModel::findOrFail($request->model_id);

        $userModel = $user->models()
            ->where('models.id', $model->id)
            ->first();

        if (!$userModel) {
            return response()->json([
                'message' => 'You are not subscribed to this AI model.'
            ], 403);
        }

        if ($userModel->pivot->status !== 'active') {
            return response()->json([
                'message' => 'Your subscription is not active.'
            ], 403);
        }

        if (
            $userModel->pivot->expires_at &&
            now()->greaterThan($userModel->pivot->expires_at)
        ) {
            return response()->json([
                'message' => 'Your subscription has expired.'
            ], 403);
        }


        $response = $aiService->chat(
            $model,
            $request->message
        );

        return response()->json([
            'model' => $model->name,
            'provider' => $model->provider,
            'response' => $response,
        ]);
    }
}
