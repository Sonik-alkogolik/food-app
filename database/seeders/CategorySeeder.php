<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * ШАГ 14: сидер категорий — минимум 10 категорий блюд с описаниями и изображениями.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Завтраки',      'slug' => 'zavtraki',       'description' => 'Идеальное начало дня',              'image_url' => '/storage/categories/breakfast.jpg'],
            ['name' => 'Супы',          'slug' => 'supy',           'description' => 'Сытные и согревающие',              'image_url' => '/storage/categories/soups.jpg'],
            ['name' => 'Вторые блюда',  'slug' => 'vtorye-blyuda',  'description' => 'Основные блюда на обед и ужин',     'image_url' => '/storage/categories/main_dishes.jpg'],
            ['name' => 'Салаты',        'slug' => 'salaty',         'description' => 'Свежие и полезные салаты',          'image_url' => '/storage/categories/salads.jpg'],
            ['name' => 'Десерты',       'slug' => 'deserty',        'description' => 'Сладкие угощения',                  'image_url' => '/storage/categories/desserts.jpg'],
            ['name' => 'Выпечка',       'slug' => 'vypechka',       'description' => 'Хлеб, пироги и булочки',            'image_url' => '/storage/categories/baking.jpg'],
            ['name' => 'Закуски',       'slug' => 'zakuski',        'description' => 'Лёгкие перекусы',                  'image_url' => '/storage/categories/snacks.jpg'],
            ['name' => 'Напитки',       'slug' => 'napitki',        'description' => 'Горячие и холодные напитки',        'image_url' => '/storage/categories/drinks.jpg'],
            ['name' => 'Соусы',         'slug' => 'soussy',         'description' => 'Домашние соусы и маринады',         'image_url' => '/storage/categories/sauces.jpg'],
            ['name' => 'Гарниры',       'slug' => 'garniry',        'description' => 'Дополнения к основным блюдам',      'image_url' => '/storage/categories/side_dishes.jpg'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
