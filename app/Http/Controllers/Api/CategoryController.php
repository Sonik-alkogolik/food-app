<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * ШАГ 19: список категорий с изображениями и счётчиком рецептов.
 * ШАГ 41: кеш списка категорий — 1 час.
 */
class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Cache::remember('api.categories.with_count', now()->addHour(), function () {
            return Category::query()
                ->withCount(['recipes' => fn ($q) => $q->active()])
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'description', 'image_url']);
        });

        return response()->json(['data' => $categories]);
    }
}
