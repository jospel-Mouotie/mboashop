<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * 1. NOTER UN PRODUIT
     * URL: POST /api/reviews/product/{productId}
     */
    public function rateProduct(Request $request, $productId)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur a bien acheté ce produit
        $hasPurchased = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', function($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez noter que les produits que vous avez achetés'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier si l'utilisateur a déjà noté ce produit
        $existingReview = Review::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà noté ce produit'
            ], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $productId,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'approved'
        ]);

        // Mettre à jour la note moyenne du produit
        $this->updateProductRating($productId);

        // Notifier le vendeur
        $this->notifySeller($productId, $request->rating);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre avis !',
            'data' => $review
        ], 201);
    }

    /**
     * 2. NOTER UNE BOUTIQUE
     * URL: POST /api/reviews/shop/{shopId}
     */
    public function rateShop(Request $request, $shopId)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur a bien acheté dans cette boutique
        $hasPurchased = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->where('shop_id', $shopId)
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez noter que les boutiques où vous avez acheté'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'approved'
        ]);

        // Mettre à jour la note moyenne de la boutique
        $this->updateShopRating($shopId);

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre avis sur la boutique !',
            'data' => $review
        ], 201);
    }

    /**
     * 3. NOTER UN LIVREUR
     * URL: POST /api/reviews/driver/{driverId}
     */
    public function rateDriver(Request $request, $driverId)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur a été livré par ce livreur
        $hasBeenDelivered = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->where('driver_id', $driverId)
            ->exists();

        if (!$hasBeenDelivered) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez noter que les livreurs qui vous ont livré'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'driver_id' => $driverId,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'approved'
        ]);

        // Mettre à jour la note moyenne du livreur
        $this->updateDriverRating($driverId);

        return response()->json([
            'success' => true,
            'message' => 'Merci d\'avoir évalué votre livreur !',
            'data' => $review
        ], 201);
    }

    /**
     * 4. MES AVIS
     * URL: GET /api/reviews/my
     */
    public function myReviews(Request $request)
    {
        $user = $request->user();

        $reviews = Review::with(['product', 'shop', 'driver'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * 5. VOIR LES AVIS D'UN PRODUIT
     * URL: GET /api/products/{id}/reviews
     */
    public function productReviews($productId)
    {
        $reviews = Review::with('user')
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $average = Review::where('product_id', $productId)
            ->where('status', 'approved')
            ->avg('rating');

        return response()->json([
            'success' => true,
            'data' => [
                'average_rating' => round($average, 1),
                'total_reviews' => $reviews->total(),
                'reviews' => $reviews
            ]
        ]);
    }

    /**
     * Mettre à jour la note moyenne d'un produit
     */
    private function updateProductRating($productId)
    {
        $average = Review::where('product_id', $productId)
            ->where('status', 'approved')
            ->avg('rating');

        Product::where('id', $productId)->update(['rating' => round($average, 1)]);
    }

    /**
     * Mettre à jour la note moyenne d'une boutique
     */
    private function updateShopRating($shopId)
    {
        $average = Review::where('shop_id', $shopId)
            ->where('status', 'approved')
            ->avg('rating');

        Shop::where('id', $shopId)->update(['rating' => round($average, 1)]);
    }

    /**
     * Mettre à jour la note moyenne d'un livreur
     */
    private function updateDriverRating($driverId)
    {
        $average = Review::where('driver_id', $driverId)
            ->where('status', 'approved')
            ->avg('rating');

        User::where('id', $driverId)->update(['rating' => round($average, 1)]);
    }

    /**
     * Notifier le vendeur d'un nouvel avis
     */
    private function notifySeller($productId, $rating)
    {
        $product = Product::with('shop.user')->find($productId);

        if ($product && $product->shop && $product->shop->user) {
            \Log::info("Notification au vendeur {$product->shop->user->name}: Nouvel avis de {$rating} étoiles");
        }
    }
}
