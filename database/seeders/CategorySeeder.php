<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::create(['name' => 'Завтраки', 'slug' => 'zavtraki']);
        Category::create(['name' => 'Супы', 'slug' => 'supy']);
        Category::create(['name' => 'Десерты', 'slug' => 'deserty']);
    }
}
