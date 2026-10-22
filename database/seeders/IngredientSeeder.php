<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Services\IngredientParser;
use App\Services\PantryStaples;
use Illuminate\Database\Seeder;

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

        $this->markPantryStaples();
    }

    /**
     * Помечает базовые продукты: соль, перец, масло, сахар, мука, воду.
     *
     * Флаг ставится по названию продукта, а не «по первому попавшемуся»:
     * соль и перец молотый есть почти в каждом рецепте, а вот перец
     * болгарский — обычный овощ, и базовым продуктом он не является.
     */
    private function markPantryStaples(): void
    {
        $staples = new PantryStaples();

        $stapleIds = [];
        $total = 0;

        Ingredient::query()->each(function (Ingredient $ingredient) use ($staples, &$stapleIds, &$total) {
            $total++;
            $isStaple = $staples->isStaple($ingredient->name);

            if ($isStaple) {
                $stapleIds[] = $ingredient->id;
            }

            if ((bool) $ingredient->is_pantry_staple !== $isStaple) {
                $ingredient->is_pantry_staple = $isStaple;
                $ingredient->save();
            }
        });

        $this->command?->info(sprintf(
            'IngredientSeeder: базовых продуктов — %d из %d (соль, перец, масло, сахар, мука, вода).',
            count($stapleIds),
            $total
        ));
    }
}
