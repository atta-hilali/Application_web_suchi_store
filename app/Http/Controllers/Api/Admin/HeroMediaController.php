<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHeroMediaRequest;
use App\Services\HeroMedia;
use Illuminate\Http\JsonResponse;

class HeroMediaController extends Controller
{
    public function update(UpdateHeroMediaRequest $request, HeroMedia $media): JsonResponse
    {
        $current = $media->update($request->validated('media_type'), $request->validated('source'), $request->file('file'), $request->validated('url'));

        return response()->json(['message' => 'Le fond d’accueil a été mis à jour.', 'hero_media' => $current]);
    }
}
