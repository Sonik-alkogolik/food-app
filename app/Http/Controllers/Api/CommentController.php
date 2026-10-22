<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Комментарии к рецептам.
 *
 * Чтение — публичное, запись — только для авторизованных: иначе комментарии
 * превращаются в анонимный поток мусора. Удалять комментарий может автор
 * или администратор.
 */
class CommentController extends Controller
{
    /**
     * GET /api/recipes/{slug}/comments — комментарии к рецепту.
     */
    public function index(Request $request, string $slug): Response
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $user = $request->user();

        $comments = $recipe->comments()
            ->with('user:id,name')
            ->latest()
            ->paginate(20)
            ->through(fn (Comment $comment) => $this->commentPayload($comment, $user));

        return response()->json($comments);
    }

    /**
     * POST /api/recipes/{slug}/comments — добавить комментарий.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ], [
            'body.required' => 'Напишите текст комментария.',
            'body.min' => 'Комментарий слишком короткий.',
            'body.max' => 'Комментарий слишком длинный (максимум 2000 символов).',
        ]);

        $comment = $recipe->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $recipe->increment('comments_count');

        return response()->json([
            'message' => 'Комментарий добавлен.',
            'data' => [
                'comment' => $this->commentPayload($comment->load('user:id,name'), $request->user()),
            ],
        ], 201);
    }

    /**
     * DELETE /api/comments/{id} — удалить комментарий.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = Comment::with('recipe')->find($id);

        if (!$comment) {
            return response()->json(['message' => 'Комментарий не найден.'], 404);
        }

        if (!$comment->canBeDeletedBy($request->user())) {
            return response()->json([
                'message' => 'Удалить комментарий может только его автор или администратор.',
            ], 403);
        }

        $recipe = $comment->recipe;
        $comment->delete();

        if ($recipe) {
            if ($recipe->comments_count > 0) {
                $recipe->decrement('comments_count');
            }
        }

        return response()->json(['message' => 'Комментарий удалён.']);
    }

    /** Рецепт по slug или по id — как и в карточке. */
    private function findRecipe(string $slugOrId): ?Recipe
    {
        $isNumeric = ctype_digit($slugOrId);

        return Recipe::query()
            ->active()
            ->where($isNumeric ? 'id' : 'slug', $slugOrId)
            ->first();
    }

    /** Ответ по одному комментарию. */
    private function commentPayload(Comment $comment, ?User $user): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'user' => [
                'id' => $comment->user?->id,
                'name' => $comment->user?->name ?? 'Аноним',
            ],
            'can_delete' => $comment->canBeDeletedBy($user),
        ];
    }
}
