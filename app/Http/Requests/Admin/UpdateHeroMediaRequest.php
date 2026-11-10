<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHeroMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $video = $this->input('media_type') === 'video';

        return [
            'media_type' => ['required', Rule::in(['image', 'video'])],
            'source' => ['required', Rule::in(['existing', 'upload', 'url'])],
            'file' => ['exclude_unless:source,upload', 'required', 'file',
                $video ? 'mimetypes:video/mp4,video/webm' : 'mimetypes:image/jpeg,image/png,image/webp',
                $video ? 'extensions:mp4,webm' : 'extensions:jpg,jpeg,png,webp',
                'max:'.config($video ? 'hero.max_video_kb' : 'hero.max_image_kb')],
            'url' => ['exclude_unless:source,url', 'required', 'url:https', 'max:2048',
                $video ? 'regex:/\.(mp4|webm)(\?.*)?$/i' : 'regex:/\.(jpe?g|png|webp)(\?.*)?$/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choisissez un fichier à importer.',
            'file.mimetypes' => 'Format non accepté : MP4/WebM pour une vidéo, JPG/PNG/WebP pour une image.',
            'file.extensions' => 'L’extension du fichier ne correspond pas au format choisi.',
            'file.max' => 'Fichier trop volumineux : maximum 30 Mo pour une vidéo et 8 Mo pour une image.',
            'url.url' => 'Saisissez un lien HTTPS valide.',
            'url.regex' => 'Utilisez un lien direct vers un fichier MP4/WebM ou JPG/PNG/WebP selon le type choisi.',
        ];
    }
}
