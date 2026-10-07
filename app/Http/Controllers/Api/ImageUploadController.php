<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ШАГ 20: загрузка изображений рецептов (POST /api/upload/image).
 * Валидация: до 5 МБ, jpg/png/webp; уникальное имя; хранение в
 * storage/app/public/recipes/uploads; ответ — публичный путь к файлу.
 */
class ImageUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'image.required' => 'Файл изображения обязателен.',
            'image.image'    => 'Можно загружать только изображения.',
            'image.mimes'    => 'Допустимые форматы: jpg, png, webp.',
            'image.max'      => 'Максимальный размер файла — 5 МБ.',
        ]);

        $file = $request->file('image');
        $uniqueName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());

        $path = $file->storeAs('recipes/uploads', $uniqueName, 'public');

        return response()->json([
            'message' => 'Изображение загружено',
            'path' => '/storage/' . $path,
            'url' => asset('storage/' . $path),
            'original_name' => $file->getClientOriginalName(),
        ], 201);
    }
}
