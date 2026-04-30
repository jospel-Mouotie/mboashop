<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Shop;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    // ==========================================
    // ROUTES PUBLIQUES (visibles par tous)
    // ==========================================

    /**
     * 1. BANNIÈRES ACTIVES (page d'accueil)
     * URL: GET /api/ads/banners
     */
    public function activeBanners()
    {
        $now = now();

        $banners = AdCampaign::with(['shop'])
            ->where('type', 'banner')
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    /**
     * 2. PRODUITS SPONSORISÉS (apparaissent en premier dans les recherches)
     * URL: GET /api/ads/sponsored-products
     */
    public function sponsoredProducts()
    {
        $now = now();

        $sponsored = AdCampaign::with(['product', 'product.photos', 'product.shop'])
            ->where('type', 'sponsored_product')
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->whereNotNull('product_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $products = [];
        foreach ($sponsored as $ad) {
            if ($ad->product && $ad->product->status === 'active') {
                $ad->product->sponsored = true;
                $products[] = $ad->product;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 3. BOUTIQUES À LA UNE
     * URL: GET /api/ads/featured-shops
     */
    public function featuredShops()
    {
        $now = now();

        $featured = AdCampaign::with(['shop'])
            ->where('type', 'featured_shop')
            ->where('status', 'active')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->orderBy('created_at', 'desc')
            ->get();

        $shops = [];
        foreach ($featured as $ad) {
            if ($ad->shop && $ad->shop->status === 'active') {
                $ad->shop->featured = true;
                $shops[] = $ad->shop;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    // ==========================================
    // ROUTES ADMIN UNIQUEMENT
    // ==========================================

    /**
     * 4. CRÉER UNE CAMPAGNE PUBLICITAIRE (admin)
     * URL: POST /api/admin/ads/campaigns
     * Body: shop_id, title, type, image_url, target_url, product_id, start_date, end_date, amount_paid
     */
    public function createCampaign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_id' => 'required|exists:shops,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:banner,sponsored_product,featured_shop',
            'image_url' => 'required_if:type,banner|nullable|string',
            'target_url' => 'nullable|string',
            'product_id' => 'required_if:type,sponsored_product|nullable|exists:products,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'amount_paid' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que le produit appartient bien à la boutique si type sponsored_product
        if ($request->type === 'sponsored_product' && $request->product_id) {
            $product = Product::find($request->product_id);
            if (!$product || $product->shop_id != $request->shop_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le produit spécifié n\'appartient pas à cette boutique'
                ], 400);
            }
        }

        $campaign = AdCampaign::create([
            'shop_id' => $request->shop_id,
            'title' => $request->title,
            'type' => $request->type,
            'image_url' => $request->image_url,
            'target_url' => $request->target_url,
            'product_id' => $request->product_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'amount_paid' => $request->amount_paid,
            'status' => 'pending',
            'impressions' => 0,
            'clicks' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campagne publicitaire créée avec succès',
            'data' => $campaign
        ], 201);
    }

    /**
     * 5. LISTER TOUTES LES CAMPAGNES (admin)
     * URL: GET /api/admin/ads/campaigns
     */
    public function allCampaigns(Request $request)
    {
        $campaigns = AdCampaign::with(['shop', 'product'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    /**
     * 6. VALIDER UNE CAMPAGNE (admin)
     * URL: PUT /api/admin/ads/campaigns/{id}/validate
     */
    public function validateCampaign($id)
    {
        $campaign = AdCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne non trouvée'
            ], 404);
        }

        $campaign->status = 'active';
        $campaign->save();

        return response()->json([
            'success' => true,
            'message' => 'Campagne validée et active',
            'data' => $campaign
        ]);
    }

    /**
     * 7. MODIFIER UNE CAMPAGNE (admin)
     * URL: PUT /api/admin/ads/campaigns/{id}
     */
    public function updateCampaign(Request $request, $id)
    {
        $campaign = AdCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'image_url' => 'nullable|string',
            'target_url' => 'nullable|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:pending,active,expired,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $campaign->update($request->only([
            'title', 'image_url', 'target_url', 'start_date', 'end_date', 'status'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Campagne mise à jour',
            'data' => $campaign
        ]);
    }

    /**
     * 8. SUPPRIMER UNE CAMPAGNE (admin)
     * URL: DELETE /api/admin/ads/campaigns/{id}
     */
    public function deleteCampaign($id)
    {
        $campaign = AdCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne non trouvée'
            ], 404);
        }

        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campagne supprimée'
        ]);
    }

    /**
     * 9. STATISTIQUES D'UNE CAMPAGNE (admin)
     * URL: GET /api/admin/ads/campaigns/{id}/stats
     */
    public function campaignStats($id)
    {
        $campaign = AdCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'success' => false,
                'message' => 'Campagne non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'impressions' => $campaign->impressions,
                'clicks' => $campaign->clicks,
                'click_through_rate' => $campaign->impressions > 0
                    ? round(($campaign->clicks / $campaign->impressions) * 100, 2)
                    : 0,
                'amount_paid' => $campaign->amount_paid,
                'cost_per_click' => $campaign->clicks > 0
                    ? round($campaign->amount_paid / $campaign->clicks, 0)
                    : 0,
            ]
        ]);
    }

    // ==========================================
    // ROUTES POUR COMMERCANTS (voir leurs pubs)
    // ==========================================

    /**
     * 10. MES CAMPAGNES (commerçant/grossiste)
     * URL: GET /api/my-ads
     */
    public function myCampaigns(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $campaigns = AdCampaign::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    // ==========================================
    // FONCTIONS UTILITAIRES (tracking)
    // ==========================================

    /**
     * 11. ENREGISTRER UN CLIC SUR UNE PUB
     * URL: POST /api/ads/{id}/click
     */
    public function trackClick($id)
    {
        $campaign = AdCampaign::find($id);

        if ($campaign) {
            $campaign->increment('clicks');

            // Enregistrer dans les stats journalières
            // À implémenter avec la table ad_stats
        }

        return response()->json(['success' => true]);
    }

    /**
     * 12. ENREGISTRER UNE IMPRESSION
     * URL: POST /api/ads/{id}/impression
     */
    public function trackImpression($id)
    {
        $campaign = AdCampaign::find($id);

        if ($campaign) {
            $campaign->increment('impressions');
        }

        return response()->json(['success' => true]);
    }
}
