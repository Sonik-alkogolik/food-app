<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Комментарии к рецептам.
 *
 * Читать и писать может любой: гость оставляет комментарий под своим
 * именем. Удалять — только автор или администратор (App\Policies\CommentPolicy).
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
     *
     * Гость пишет комментарий под именем (guest_name), пользователь —
     * от своего аккаунта.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $recipe = $this->findRecipe($slug);

        if (!$recipe) {
            return response()->json(['message' => 'Рецепт не найден.'], 404);
        }

        $user = $request->user();

        $rules = [
            'body' => ['required', 'string', 'min:2', 'max:2000'],
            'guest_name' => ['nullable', 'string', 'max:50'],
        ];

        if (!$user) {
            // Гостю нечем подписаться, кроме имени. Без него комментарий
            // остался бы анонимным, и так его и удалить нельзя.
            $rules['guest_name'] = ['required', 'string', 'max:50'];
        }

        $data = $request->validate($rules, [
            'body.required' => 'Напишите текст комментария.',
            'body.min' => 'Комментарий слишком короткий.',
            'body.max' => 'Комментарий слишком длинный (максимум 2000 символов).',
            'guest_name.required' => 'Напишите, как вас зовут.',
            'guest_name.max' => 'Имя слишком длинное (максимум 50 символов).',
        ]);

        $comment = $recipe->comments()->create([
            'user_id' => $user?->id,
            'guest_name' => $user ? null : trim((string) $data['guest_name']),
            'body' => $data['body'],
        ]);

        $recipe->increment('comments_count');

        return response()->json([
            'message' => 'Комментарий добавлен.',
            'data' => [
                'comment' => $this->commentPayload($comment->load('user:id,name'), $user),
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

        if (!Gate::forUser($request->user())->allows('delete', $comment)) {
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
                'name' => $comment->authorName(),
            ],
            'is_guest' => $comment->isGuest(),
            'can_delete' => Gate::forUser($user)->allows('delete', $comment),
        ];
    }
}
