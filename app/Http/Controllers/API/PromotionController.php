<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromotionController extends Controller
{
    /**
     * 1. LISTER TOUTES LES PROMOTIONS (admin)
     * URL: GET /api/admin/promotions
     */
    public function index(Request $request)
    {
        $promotions = Promotion::with(['product', 'shop'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $promotions
        ]);
    }

    /**
     * 2. PROMOTIONS ACTIVES (visibles par tous)
     * URL: GET /api/promotions
     */
    public function activePromotions()
    {
        $now = now();

        $promotions = Promotion::with(['product', 'product.photos', 'product.shop'])
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('created_at', 'desc')
            ->get();

        // Ajouter le prix réduit à chaque produit
        foreach ($promotions as $promotion) {
            if ($promotion->product) {
                $promotion->product->original_price = $promotion->product->price;
                $promotion->product->discounted_price = $promotion->getDiscountedPrice($promotion->product->price);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $promotions
        ]);
    }

    /**
     * 3. FLASH SALES (promotions à durée limitée)
     * URL: GET /api/promotions/flash-sales
     */
    public function flashSales()
    {
        $now = now();

        $flashSales = Promotion::with(['product', 'product.photos', 'product.shop'])
            ->where('status', 'active')
            ->where('is_flash_sale', true)
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('end_date', 'asc')
            ->get();

        // Calculer le temps restant pour chaque flash sale
        foreach ($flashSales as $sale) {
            $sale->time_remaining = $sale->end_date->diffInSeconds(now());
            $sale->time_remaining_human = $sale->end_date->diffForHumans(now(), ['parts' => 3]);

            if ($sale->product) {
                $sale->product->original_price = $sale->product->price;
                $sale->product->discounted_price = $sale->getDiscountedPrice($sale->product->price);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $flashSales
        ]);
    }

    /**
     * 4. VOIR UNE PROMOTION SPÉCIFIQUE
     * URL: GET /api/promotions/{id}
     */
    public function show($id)
    {
        $promotion = Promotion::with(['product', 'shop'])->find($id);

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Promotion non trouvée'
            ], 404);
        }

        if ($promotion->product) {
            $promotion->product->discounted_price = $promotion->getDiscountedPrice($promotion->product->price);
        }

        return response()->json([
            'success' => true,
            'data' => $promotion
        ]);
    }

    /**
     * 5. CRÉER UNE PROMOTION (commerçant/grossiste)
     * URL: POST /api/promotions
     * Body: product_id, discount_percentage, start_date, end_date, is_flash_sale
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop || $shop->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Votre boutique doit être active pour créer des promotions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'discount_percentage' => 'required|integer|min:1|max:100',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'is_flash_sale' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que le produit appartient bien à la boutique
        $product = Product::where('shop_id', $shop->id)->find($request->product_id);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit ne vous appartient pas'
            ], 403);
        }

        // Vérifier qu'il n'y a pas déjà une promotion active sur ce produit
        $existingPromotion = Promotion::where('product_id', $request->product_id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();

        if ($existingPromotion) {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit a déjà une promotion active'
            ], 400);
        }

        $promotion = Promotion::create([
            'product_id' => $request->product_id,
            'shop_id' => $shop->id,
            'discount_percentage' => $request->discount_percentage,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_flash_sale' => $request->is_flash_sale ?? false,
            'status' => 'pending' // En attente de validation par l'admin
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Promotion créée. En attente de validation par l\'administrateur.',
            'data' => $promotion
        ], 201);
    }

    /**
     * 6. MODIFIER UNE PROMOTION
     * URL: PUT /api/promotions/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $shop = $user->shop;
        $promotion = Promotion::where('shop_id', $shop->id)->find($id);

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Promotion non trouvée'
            ], 404);
        }

        if ($promotion->status === 'active' && $promotion->start_date <= now()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette promotion a déjà commencé, vous ne pouvez plus la modifier'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'discount_percentage' => 'sometimes|integer|min:1|max:100',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_flash_sale' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $promotion->update($request->only([
            'discount_percentage', 'start_date', 'end_date', 'is_flash_sale'
        ]));

        // Remettre en attente si modifiée
        $promotion->status = 'pending';
        $promotion->save();

        return response()->json([
            'success' => true,
            'message' => 'Promotion mise à jour. En attente de nouvelle validation.',
            'data' => $promotion
        ]);
    }

    /**
     * 7. SUPPRIMER UNE PROMOTION
     * URL: DELETE /api/promotions/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $shop = $user->shop;
        $promotion = Promotion::where('shop_id', $shop->id)->find($id);

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Promotion non trouvée'
            ], 404);
        }

        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promotion supprimée avec succès'
        ]);
    }

    /**
     * 8. MES PROMOTIONS (commerçant/grossiste)
     * URL: GET /api/my-promotions
     */
    public function myPromotions(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $promotions = Promotion::with(['product'])
            ->where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $promotions
        ]);
    }

    /**
     * 9. VALIDER UNE PROMOTION (admin uniquement)
     * URL: PUT /api/admin/promotions/{id}/validate
     */
    public function validatePromotion($id)
    {
        $promotion = Promotion::find($id);

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Promotion non trouvée'
            ], 404);
        }

        $promotion->status = 'active';
        $promotion->save();

        return response()->json([
            'success' => true,
            'message' => 'Promotion validée avec succès',
            'data' => $promotion
        ]);
    }

    /**
     * 10. PROMOTIONS EN ATTENTE (admin)
     * URL: GET /api/admin/promotions/pending
     */
    public function pendingPromotions()
    {
        $promotions = Promotion::with(['product', 'shop'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $promotions
        ]);
    }
}
