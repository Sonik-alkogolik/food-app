<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Support\IngredientParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Списки покупок.
 *
 * Список — личный: он принадлежит пользователю, и чужие списки не видно
 * (404, а не 403 — иначе по коду ответа можно было бы перебором узнать,
 * существует ли чужой список).
 *
 * Позиции добавляются по одной или разом: «добавь в список ингредиенты
 * рецепта» вставляет их одной транзакцией, чтобы в списке не оказалось
 * половины продуктов, если что-то пошло не так.
 */
class ShoppingListController extends Controller
{
    /** Заголовок списка, который создаётся автоматически. */
    private const DEFAULT_TITLE = 'Мой список';

    /**
     * GET /api/shopping-list — списки пользователя с позициями.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $lists = ShoppingList::where('user_id', $user->id)
            ->with('items')
            ->orderBy('created_at')
            ->get();

        // У нового пользователя сразу есть один список: иначе пустая
        // страница выглядит как ошибка, а не как «списков пока нет».
        if ($lists->isEmpty()) {
            $lists = collect([ShoppingList::create([
                'user_id' => $user->id,
                'title' => self::DEFAULT_TITLE,
            ])->load('items')]);
        }

        return response()->json([
            'data' => $lists->map(fn (ShoppingList $list) => $this->payload($list))->values(),
        ]);
    }

    /**
     * POST /api/shopping-list — создать список.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ], [
            'title.required' => 'Введите название списка.',
            'title.max' => 'Название списка не длиннее 120 символов.',
        ]);

        $list = ShoppingList::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
        ]);

        return response()->json([
            'message' => 'Список создан.',
            'data' => $this->payload($list->load('items')),
        ], 201);
    }

    /**
     * PATCH /api/shopping-list/{id} — переименовать список.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ], [
            'title.required' => 'Введите название списка.',
            'title.max' => 'Название списка не длиннее 120 символов.',
        ]);

        $list->update(['title' => $data['title']]);

        return response()->json([
            'message' => 'Список переименован.',
            'data' => $this->payload($list->load('items')),
        ]);
    }

    /**
     * DELETE /api/shopping-list/{id} — удалить список с позициями.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $list->delete();

        return response()->json(['message' => 'Список удалён.']);
    }

    /**
     * POST /api/shopping-list/{id}/items — добавить позицию.
     */
    public function storeItem(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:30'],
        ], [
            'name.required' => 'Введите, что купить.',
            'name.max' => 'Название не длиннее 150 символов.',
            'amount.numeric' => 'Количество должно быть числом.',
        ]);

        $item = $list->items()->create([
            'name' => $data['name'],
            'amount' => $data['amount'] ?? null,
            'unit' => $data['unit'] ?? null,
            'checked' => false,
            'position' => $this->nextPosition($list),
        ]);

        return response()->json([
            'message' => 'Позиция добавлена.',
            'data' => $this->itemPayload($item),
        ], 201);
    }

    /**
     * PATCH /api/shopping-list/{id}/items/{itemId} — изменить позицию
     * (отметить «куплено», переименовать, поменять количество).
     */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $item = $list->items()->whereKey($itemId)->first();

        if (! $item) {
            return $this->notFound();
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'checked' => ['sometimes', 'boolean'],
        ], [
            'name.max' => 'Название не длиннее 150 символов.',
            'amount.numeric' => 'Количество должно быть числом.',
        ]);

        foreach (['name', 'amount', 'unit', 'checked'] as $field) {
            if (array_key_exists($field, $data)) {
                $item->{$field} = $data[$field];
            }
        }

        $item->save();

        return response()->json([
            'message' => 'Позиция обновлена.',
            'data' => $this->itemPayload($item),
        ]);
    }

    /**
     * DELETE /api/shopping-list/{id}/items/{itemId} — убрать позицию.
     */
    public function destroyItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $item = $list->items()->whereKey($itemId)->first();

        if (! $item) {
            return $this->notFound();
        }

        $item->delete();

        return response()->json(['message' => 'Позиция удалена.']);
    }

    /**
     * POST /api/shopping-list/{id}/items/bulk — добавить ингредиенты рецепта.
     *
     * Все позиции вставляются одной транзакцией: если в середине что-то
     * пошло не так, в списке не должно остаться половины продуктов.
     * Позиции с тем же названием не дублируются, а количества
     * складываются — так «добавить рецепт дважды» не превращается
     * в две одинаковые строки.
     */
    public function bulkAdd(Request $request, int $id): JsonResponse
    {
        $list = $this->findList($request, $id);

        if (! $list) {
            return $this->notFound();
        }

        $data = $request->validate([
            'recipe' => ['required', 'string', 'max:150'],
            'servings' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'recipe.required' => 'Укажите рецепт.',
            'servings.integer' => 'Число порций должно быть целым.',
        ]);

        $recipe = $this->findRecipe((string) $data['recipe']);

        if (! $recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $servings = (int) ($data['servings'] ?? $recipe->servings);
        $base = max(1, (int) $recipe->servings);
        $factor = $servings / $base;

        $parsed = IngredientParser::parseMany(
            preg_split('/\r\n|\r|\n/', (string) $recipe->ingredients) ?: []
        );

        $existing = $list->items()->get()->keyBy(fn (ShoppingListItem $item) => mb_strtolower($item->name));

        $rows = [];
        $merged = 0;

        foreach ($parsed as $position => $ingredient) {
            $amount = $ingredient['amount'] === null
                ? null
                : round($ingredient['amount'] * $factor, 2);

            $key = mb_strtolower($ingredient['name']);
            $current = $existing->get($key);

            // Такая позиция уже есть — складываем количество вместо дубля.
            if ($current instanceof ShoppingListItem && ! $current->checked) {
                if ($amount !== null) {
                    $current->amount = round((float) $current->amount + $amount, 2);
                    $current->save();
                    $merged++;
                }

                continue;
            }

            $rows[] = [
                'shopping_list_id' => $list->id,
                'recipe_id' => $recipe->id,
                'name' => $ingredient['name'],
                'amount' => $amount,
                'unit' => $ingredient['unit'],
                'checked' => false,
                'position' => $this->nextPosition($list) + count($rows),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::transaction(function () use ($rows) {
                ShoppingListItem::insert($rows);
            });
        }

        $list->load('items');

        $added = count($rows);

        return response()->json([
            'message' => $merged > 0
                ? "Добавлено позиций: {$added}, уточнено: {$merged}."
                : "Добавлено позиций: {$added}.",
            'data' => $this->payload($list),
        ], 201);
    }

    /** Список пользователя по id (чужой список не виден). */
    private function findList(Request $request, int $id): ?ShoppingList
    {
        return ShoppingList::where('user_id', $request->user()->id)->whereKey($id)->first();
    }

    private function findRecipe(string $slugOrId): ?Recipe
    {
        return ctype_digit($slugOrId)
            ? Recipe::query()->active()->find((int) $slugOrId)
            : Recipe::query()->active()->where('slug', $slugOrId)->first();
    }

    /** Следующая позиция в списке: новые позиции в конце. */
    private function nextPosition(ShoppingList $list): int
    {
        return (int) $list->items()->max('position') + 1;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Список не найден.'], 404);
    }

    private function payload(ShoppingList $list): array
    {
        return [
            'id' => $list->id,
            'title' => $list->title,
            'created_at' => $list->created_at?->toIso8601String(),
            'items' => $list->items->map(fn (ShoppingListItem $item) => $this->itemPayload($item))->values(),
        ];
    }

    private function itemPayload(ShoppingListItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'amount' => $item->amount,
            'unit' => $item->unit,
            'amount_label' => $item->amountLabel(),
            'checked' => $item->checked,
            'recipe_id' => $item->recipe_id,
            'position' => $item->position,
        ];
    }
}
