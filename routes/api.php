<?

use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserAuthController;
use Illuminate\Support\Facades\Route;
use Gemini\Enums\ModelVariation;
use Gemini\GeminiHelper;
use Gemini\Laravel\Facades\Gemini;

Route::post('/register', [UserAuthController::class, 'register']);
Route::post('/login', [UserAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/chat', [ChatController::class, 'chat']);
    Route::post('/logout', [UserAuthController::class, 'logout']);

    Route::post('/Gemini', function(){
        $result = Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent('Hello');
        $result->text(); // Hello! How can I assist you today?

        // Helper method usage
        // $result = Gemini::generativeModel(
        //     model: GeminiHelper::generateGeminiModel(
        //         variation: ModelVariation::FLASH,
        //         generation: 2.5,
        //         version: "preview-04-17"
        //     ), // models/gemini-2.5-flash-preview-04-17
        // )->generateContent('Hello');
        // $result->text(); // Hello! How can I assist you today
    
    });
});
