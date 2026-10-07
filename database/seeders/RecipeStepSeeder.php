<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ШАГ 16: сидер шагов приготовления.
 * Для каждого рецепта создаёт от 3 до 8 логичных шагов (заголовки, описания,
 * примерное время выполнения). Шаги берутся из каталога блюд и дополняются
 * универсальными «подготовительными» и «сервировочными» этапами, чтобы
 * количество всегда попадало в диапазон 3–8. Вставка — пакетами по 100 строк.
 */
class RecipeStepSeeder extends Seeder
{
    private const CHUNK_SIZE = 100;

    public function run(): void
    {
        mt_srand(20261008);

        $now = now()->toDateTimeString();
        $buffer = [];
        $totalSteps = 0;

        // Чанкуем рецепты, чтобы не переполнить память при 1000+ записях
        Recipe::query()->chunkById(100, function ($recipes) use (&$buffer, &$totalSteps, $now) {
            foreach ($recipes as $recipe) {
                $steps = $this->buildSteps($recipe);

                foreach ($steps as $index => [$title, $description, $duration]) {
                    $stepNumber = $index + 1;
                    $buffer[] = [
                        'recipe_id'        => $recipe->id,
                        'step_number'      => $stepNumber,
                        'title'            => $title,
                        'description'      => $description,
                        'image_url'        => "/storage/recipes/steps/recipe-{$recipe->id}/step-{$stepNumber}.jpg",
                        'duration_minutes' => $duration,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                    $totalSteps++;

                    if (count($buffer) >= self::CHUNK_SIZE) {
                        DB::table('recipe_steps')->insert($buffer);
                        $buffer = [];
                    }
                }
            }
        });

        if ($buffer !== []) {
            DB::table('recipe_steps')->insert($buffer);
        }

        $this->command?->info("RecipeStepSeeder: создано {$totalSteps} шагов.");
    }

    /**
     * Логичная последовательность шагов для рецепта.
     * Берём шаги из instructions рецепта (они уже осмысленные), если их мало —
     * добавляем типовые «Подготовка ингредиентов» / «Подача к столу».
     *
     * @return array<int, array{0:string,1:string,2:int}> [title, description, duration]
     */
    private function buildSteps(Recipe $recipe): array
    {
        $steps = $this->parseInstructions($recipe);
        $target = rand(3, 8);

        // Дополняем недостающие шаги типовыми этапами
        if (count($steps) < $target) {
            $fillers = [
                ['Подготовка ингредиентов', 'Проверьте наличие всех продуктов по списку. Отмерьте нужное количество, освободите рабочее место и подготовьте необходимую посуду и инвентарь.', rand(2, 6)],
                ['Нарезка овощей', 'Промойте и очистите овощи. Нарежьте их равномерно — так они приготовятся одновременно и блюдо будет выглядеть аккуратно.', rand(4, 10)],
                ['Обжаривание мяса', 'Разогрейте сковороду с маслом до лёгкого дымка. Обжаривайте мясо порциями, не перегружая посуду, чтобы получилась румяная корочка.', rand(5, 15)],
                ['Добавление специй', 'Всыпьте соль, перец и ароматные специи, перемешайте и дайте им раскрыться в тепле буквально минуту — блюдо станет заметно насыщеннее.', rand(2, 4)],
                ['Тушение', 'Убавьте огонь до минимального, накройте крышкой и томите блюдо, периодически помешивая, пока оно не станет мягким и нежным.', rand(10, 30)],
                ['Подача к столу', 'Дайте блюду немного настояться, выложите на сервировочное блюдо, украсьте свежей зеленью и подавайте горячим к столу.', rand(2, 5)],
            ];

            foreach ($fillers as $filler) {
                if (count($steps) >= $target) {
                    break;
                }
                // вставляем в начало или конец, сохраняя логику
                if (!str_contains(mb_strtolower($recipe->instructions ?? ''), mb_strtolower($filler[0]))) {
                    if (in_array($filler[0], ['Подача к столу'], true)) {
                        $steps[] = $filler;
                    } else {
                        array_splice($steps, min(1, count($steps)), 0, [$filler]);
                    }
                }
            }
        }

        // Если слишком много — берём первые N, гарантируя минимум 3
        if (count($steps) > $target) {
            $steps = array_slice($steps, 0, $target);
        }
        if (count($steps) < 3) {
            $steps = array_slice($steps, 0, 2);
            $steps[] = ['Подача к столу', 'Готовое блюдо переложите на тарелку, украсьте по вкусу и подавайте сразу, пока оно не остыло.', rand(2, 5)];
        }

        return array_slice($steps, 0, 8);
    }

    /** Разбирает нумерованные инструкции рецепта в шаги [title, description, duration] */
    private function parseInstructions(Recipe $recipe): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $recipe->instructions);
        $steps = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Формат BulkRecipeSeeder: "1. Заголовок: описание"
            if (preg_match('/^(\d+)\.\s*([^:]{3,80}):\s*(.+)$/u', $line, $m)) {
                $title = trim($m[2]);
                $description = trim($m[3]);
            } else {
                $title = 'Этап приготовления';
                $description = preg_replace('/^\d+\.\s*/', '', $line);
            }

            $steps[] = [$title, $description, rand(2, 30)];
        }

        return $steps;
    }
}
