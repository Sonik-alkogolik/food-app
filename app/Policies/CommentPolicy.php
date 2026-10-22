<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Права на комментарии.
 *
 * Удалить комментарий может его автор или администратор. Всё остальное —
 * нельзя: иначе любой прохожий стирал бы чужие мнения.
 *
 * Гость удалить не может: он не пользователь сайта, и проверить, что
 * комментарий его, нечем. Своего гостевого комментария гость тоже не
 * сможет убрать — поэтому интерфейс и не показывает ему кнопку удаления.
 */
class CommentPolicy
{
    public function delete(?User $user, Comment $comment): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $comment->user_id !== null && $comment->user_id === $user->id;
    }
}
