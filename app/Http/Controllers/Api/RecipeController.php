<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function index()
    {
        return response()->json(
            Recipe::with('category')->where('is_active', true)->paginate(9)
        );
    }
}
