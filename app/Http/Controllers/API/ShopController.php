<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    // 1. Créer une boutique
    public function create(Request $request)
    {
        $user = $request->user();

        // Vérifier si l'utilisateur a déjà une boutique
        if ($user->shop) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà une boutique'
            ], 400);
        }

        // Vérifier que l'utilisateur est commerçant ou grossiste
        if (!in_array($user->role, ['commercant', 'grossiste'])) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les commerçants et grossistes peuvent créer une boutique'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $shop = Shop::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'slug' => Shop::generateSlug($request->name),
            'description' => $request->description,
            'address' => $request->address,
            'city' => $request->city,
            'phone' => $request->phone ?? $user->phone,
            'type' => $user->role,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Boutique créée. En attente de validation par l\'administrateur.',
            'shop' => $shop
        ], 201);
    }

    // 2. Voir sa boutique
    public function myShop(Request $request)
    {
        $user = $request->user();

        if (!$user->shop) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas encore de boutique'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'shop' => $user->shop
        ]);
    }

    // 3. Modifier sa boutique
    public function update(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'phone' => 'nullable|string',
            'logo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $shop->update($request->only(['name', 'description', 'address', 'city', 'phone', 'logo']));

        if ($request->has('name') && $request->name !== $shop->name) {
            $shop->slug = Shop::generateSlug($request->name);
            $shop->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Boutique mise à jour',
            'shop' => $shop
        ]);
    }
}
