<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // 1. Lister toutes les catégories (pagination)
    public function index()
    {
        $categories = Category::paginate(20);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // 2. Voir une catégorie spécifique
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    // 3. Voir l'arborescence (catégories parents avec leurs enfants)
    public function tree()
    {
        $categories = Category::whereNull('parent_id')
            ->with('children')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    // 4. Voir les produits d'une catégorie (pour plus tard quand on aura Product)
    public function products($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Pour l'instant, on retourne juste la catégorie
        // Plus tard, on ajoutera $category->products
        return response()->json([
            'success' => true,
            'category' => $category,
            'products' => [] // À remplir quand Product existera
        ]);
    }
}
