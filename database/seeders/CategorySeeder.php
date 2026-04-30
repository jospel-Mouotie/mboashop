<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Électronique',
                'description' => 'Téléphones, ordinateurs, tablettes, accessoires high-tech',
                'parent_id' => null
            ],
            [
                'name' => 'Téléphones',
                'description' => 'Smartphones, téléphones portables',
                'parent_id' => 1  // Enfant de Électronique
            ],
            [
                'name' => 'Ordinateurs',
                'description' => 'PC, laptops, accessoires informatiques',
                'parent_id' => 1  // Enfant de Électronique
            ],
            [
                'name' => 'Mode',
                'description' => 'Vêtements, chaussures, accessoires de mode',
                'parent_id' => null
            ],
            [
                'name' => 'Vêtements Homme',
                'description' => 'Chemises, pantalons, t-shirts pour homme',
                'parent_id' => 4  // Enfant de Mode
            ],
            [
                'name' => 'Vêtements Femme',
                'description' => 'Robes, jupes, blouses pour femme',
                'parent_id' => 4  // Enfant de Mode
            ],
            [
                'name' => 'Maison & Décoration',
                'description' => 'Meubles, décoration, literie, électroménager',
                'parent_id' => null
            ],
            [
                'name' => 'Beauté & Soins',
                'description' => 'Cosmétiques, parfums, soins de la peau',
                'parent_id' => null
            ],
            [
                'name' => 'Alimentation',
                'description' => 'Produits frais, épicerie, boissons',
                'parent_id' => null
            ],
            [
                'name' => 'Sport',
                'description' => 'Équipement sportif, vêtements de sport, accessoires',
                'parent_id' => null
            ],
            [
                'name' => 'Jouets & Jeux',
                'description' => 'Jouets pour enfants, jeux de société',
                'parent_id' => null
            ],
            [
                'name' => 'Santé & Bien-être',
                'description' => 'Compléments alimentaires, équipement médical',
                'parent_id' => null
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => Str::slug($category['name']),
                'description' => $category['description'],
                'parent_id' => $category['parent_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
