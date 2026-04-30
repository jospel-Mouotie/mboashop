<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InterestController extends Controller
{
    // 1. Ajouter des centres d'intérêt (plusieurs catégories à la fois)
    public function add(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Attacher les catégories à l'utilisateur (ignore les doublons)
        $user->interests()->syncWithoutDetaching($request->category_ids);

        return response()->json([
            'success' => true,
            'message' => 'Centres d\'intérêt ajoutés avec succès',
            'interests' => $user->interests()->get()
        ]);
    }

    // 2. Récupérer les centres d'intérêt de l'utilisateur connecté
    public function myInterests(Request $request)
    {
        $user = $request->user();

        $interests = $user->interests()->get();

        return response()->json([
            'success' => true,
            'data' => $interests
        ]);
    }

    // 3. Remplacer tous les centres d'intérêt (modification complète)
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Synchroniser (remplace tous les anciens par les nouveaux)
        $user->interests()->sync($request->category_ids);

        return response()->json([
            'success' => true,
            'message' => 'Centres d\'intérêt mis à jour',
            'interests' => $user->interests()->get()
        ]);
    }

    // 4. Supprimer un centre d'intérêt spécifique
    public function remove(Request $request, $categoryId)
    {
        $user = $request->user();

        if (!Category::find($categoryId)) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $user->interests()->detach($categoryId);

        return response()->json([
            'success' => true,
            'message' => 'Centre d\'intérêt supprimé',
            'interests' => $user->interests()->get()
        ]);
    }

    // 5. Recommandations (produits basés sur les centres d'intérêt)
    // À compléter plus tard quand on aura les produits
    public function recommendations(Request $request)
    {
        $user = $request->user();

        // Récupérer les IDs des catégories préférées
        $interestIds = $user->interests()->pluck('categories.id')->toArray();

        // Pour l'instant, retourner juste les catégories
        // Plus tard, on ajoutera les produits de ces catégories
        return response()->json([
            'success' => true,
            'interest_categories' => $interestIds,
            'recommendations' => [] // À remplir quand Product existera
        ]);
    }
}
