<?php

namespace App\Policies;

use App\Models\RecipeResult;
use App\Models\User;

/**
 * Права на фотоотчёты.
 *
 * Удалить фотоотчёт может его автор или администратор — так же, как
 * комментарий (App\Policies\CommentPolicy). Гостю чужая фотография
 * не принадлежит, а свою он удалить не может: гость не пользователь
 * сайта, и проверить, что отчёт его, нечем.
 */
class RecipeResultPolicy
{
    public function delete(?User $user, RecipeResult $result): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $result->user_id !== null && $result->user_id === $user->id;
    }
}
