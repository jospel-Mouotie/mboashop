<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * 1. VOIR LE PANIER
     * URL: GET /api/cart
     * Retourne tous les articles du panier avec les détails des produits
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $cartItems = CartItem::with(['product', 'product.photos', 'product.shop'])
            ->where('user_id', $user->id)
            ->get();

        // Calculer les totaux
        $total = 0;
        foreach ($cartItems as $item) {
            // Vérifier si le produit a une promotion active
            $item->product->current_price = $this->getProductPrice($item->product);
            $item->subtotal = $item->quantity * $item->product->current_price;
            $total += $item->subtotal;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems,
                'total_items' => $cartItems->sum('quantity'),
                'total_price' => $total,
                'shipping_cost' => $this->calculateShipping($total),
                'grand_total' => $total + $this->calculateShipping($total),
            ]
        ]);
    }

    /**
     * 2. AJOUTER UN PRODUIT AU PANIER
     * URL: POST /api/cart/add
     * Body: product_id, quantity, options
     */
    public function add(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($request->product_id);

        // Vérifier que le produit est actif et en stock
        if ($product->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Ce produit n\'est pas disponible'
            ], 400);
        }

        if ($product->stock < ($request->quantity ?? 1)) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant. Il reste ' . $product->stock . ' unités.'
            ], 400);
        }

        // Vérifier si le produit est déjà dans le panier
        $existingItem = CartItem::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingItem) {
            // Mettre à jour la quantité
            $newQuantity = $existingItem->quantity + ($request->quantity ?? 1);

            if ($product->stock < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuffisant pour la quantité demandée'
                ], 400);
            }

            $existingItem->update([
                'quantity' => $newQuantity
            ]);

            $cartItem = $existingItem;
        } else {
            // Créer un nouvel article
            $cartItem = CartItem::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity ?? 1,
                'price_at_add' => $product->price,
                'options' => $request->options,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit ajouté au panier',
            'data' => $cartItem->load('product')
        ]);
    }

    /**
     * 3. MODIFIER LA QUANTITÉ D'UN ARTICLE
     * URL: PUT /api/cart/update/{id}
     * Body: quantity
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $cartItem = CartItem::where('user_id', $user->id)
            ->with('product')
            ->find($id);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier le stock
        if ($cartItem->product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant. Il reste ' . $cartItem->product->stock . ' unités.'
            ], 400);
        }

        $cartItem->update([
            'quantity' => $request->quantity
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Quantité mise à jour',
            'data' => $cartItem->load('product')
        ]);
    }

    /**
     * 4. SUPPRIMER UN ARTICLE DU PANIER
     * URL: DELETE /api/cart/remove/{id}
     */
    public function remove(Request $request, $id)
    {
        $user = $request->user();

        $cartItem = CartItem::where('user_id', $user->id)->find($id);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Article retiré du panier'
        ]);
    }

    /**
     * 5. VIDER TOUT LE PANIER
     * URL: DELETE /api/cart/clear
     */
    public function clear(Request $request)
    {
        $user = $request->user();

        CartItem::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Panier vidé avec succès'
        ]);
    }

    /**
     * 6. SYNCHRONISER LE PANIER (entre plusieurs appareils)
     * URL: POST /api/cart/sync
     * Body: items (tableau d'articles)
     */
    public function sync(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Supprimer le panier actuel
        CartItem::where('user_id', $user->id)->delete();

        // Ajouter les nouveaux articles
        $addedItems = [];
        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);

            if ($product && $product->status === 'active' && $product->stock >= $item['quantity']) {
                $cartItem = CartItem::create([
                    'user_id' => $user->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_at_add' => $product->price,
                    'options' => $item['options'] ?? null,
                ]);
                $addedItems[] = $cartItem;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Panier synchronisé',
            'data' => $addedItems
        ]);
    }

    /**
     * 7. COMPTER LE NOMBRE D'ARTICLES DANS LE PANIER
     * URL: GET /api/cart/count
     */
    public function count(Request $request)
    {
        $user = $request->user();

        $count = CartItem::where('user_id', $user->id)->sum('quantity');

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

    /**
     * Fonction utilitaire : obtenir le prix actuel (avec promo)
     */
    private function getProductPrice($product)
    {
        if ($product->activePromotion && $product->activePromotion->isActive()) {
            return $product->activePromotion->getDiscountedPrice($product->price);
        }
        return $product->price;
    }

    /**
     * Fonction utilitaire : calculer les frais de livraison
     */
    private function calculateShipping($total)
    {
        // Livraison gratuite si commande > 50000 FCFA
        if ($total >= 50000) {
            return 0;
        }
        // Sinon frais fixes
        return 1500;
    }
}
