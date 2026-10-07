<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\IngredientParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $parser = new IngredientParser();

        Recipe::with('ingredientsRelation')->get()->each(function (Recipe $recipe) use ($parser) {
            $parsed = $parser->parse((string) $recipe->ingredients);
            $detachIds = Ingredient::whereIn('slug', collect($parsed)->pluck('slug'))->pluck('id');
            $recipe->ingredientsRelation()->detach($detachIds);

            foreach ($parsed as $row) {
                $ingredient = Ingredient::firstOrCreate(
                    ['slug' => $row['slug']],
                    ['name' => $row['name']]
                );
                $recipe->ingredientsRelation()->syncWithoutDetaching([
                    $ingredient->id => ['quantity' => $row['quantity']],
                ]);
            }
        });
    }
}
