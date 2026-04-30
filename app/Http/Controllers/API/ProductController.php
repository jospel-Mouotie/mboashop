<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * 1. LISTER LES PRODUITS (avec filtres)
     * URL: GET /api/products
     * Paramètres possibles :
     *   - category_id : filtrer par catégorie
     *   - shop_id : filtrer par boutique
     *   - min_price : prix minimum
     *   - max_price : prix maximum
     *   - q : recherche textuelle
     *   - sort : price_asc, price_desc, newest
     */
    public function index(Request $request)
    {
        $query = Product::with(['shop', 'category', 'photos'])
            ->where('status', 'active');

        // Filtre par catégorie
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtre par boutique
        if ($request->has('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        // Filtre par prix min
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        // Filtre par prix max
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Recherche textuelle
        if ($request->has('q')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->q . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->q . '%');
            });
        }

        // Tri
        switch ($request->get('sort', 'newest')) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 2. VOIR UN PRODUIT SPÉCIFIQUE
     * URL: GET /api/products/{id}
     */
    public function show($id)
    {
        $product = Product::with(['shop', 'category', 'photos'])
            ->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Incrémenter le compteur de vues
        $product->increment('views');

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * 3. CRÉER UN PRODUIT (commerçant/grossiste)
     * URL: POST /api/products
     * Body: name, category_id, description, price, stock, unit
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        // Vérifier que l'utilisateur a une boutique
        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord créer une boutique'
            ], 400);
        }

        // Vérifier que la boutique est active
        if ($shop->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Votre boutique doit être validée par l\'administrateur'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
            'unit' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create([
            'shop_id' => $shop->id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'slug' => $this->generateSlug($request->name, $shop->id),
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'unit' => $request->unit,
            'status' => 'active',
            'views' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product
        ], 201);
    }

    /**
     * 4. MODIFIER UN PRODUIT
     * URL: PUT /api/products/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $shop = $user->shop;
        $product = Product::where('shop_id', $shop->id)->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'sometimes|integer|min:0',
            'stock' => 'sometimes|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'status' => 'sometimes|in:active,inactive,out_of_stock'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($request->only([
            'name', 'category_id', 'description', 'price', 'stock', 'unit', 'status'
        ]));

        if ($request->has('name')) {
            $product->slug = $this->generateSlug($request->name, $shop->id);
            $product->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour',
            'data' => $product
        ]);
    }

    /**
     * 5. SUPPRIMER UN PRODUIT
     * URL: DELETE /api/products/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $shop = $user->shop;
        $product = Product::where('shop_id', $shop->id)->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Supprimer les photos associées
        foreach ($product->photos as $photo) {
            $photo->delete();
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }

    /**
     * 6. AJOUTER DES PHOTOS À UN PRODUIT
     * URL: POST /api/products/{id}/photos
     * Body: photos[] (tableau d'URLs ou base64)
     */
    public function addPhotos(Request $request, $productId)
    {
        $user = $request->user();
        $shop = $user->shop;
        $product = Product::where('shop_id', $shop->id)->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'photos' => 'required|array',
            'photos.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $uploadedPhotos = [];
        $currentPhotoCount = $product->photos()->count();

        foreach ($request->photos as $index => $photoUrl) {
            // La première photo devient la photo principale si aucune photo n'existe
            $isPrimary = ($index === 0 && $currentPhotoCount === 0);

            $productPhoto = ProductPhoto::create([
                'product_id' => $product->id,
                'image_path' => $photoUrl,
                'is_primary' => $isPrimary,
                'order' => $currentPhotoCount + $index
            ]);

            $uploadedPhotos[] = $productPhoto;
        }

        return response()->json([
            'success' => true,
            'message' => 'Photos ajoutées avec succès',
            'data' => $uploadedPhotos
        ]);
    }

    /**
     * 7. SUPPRIMER UNE PHOTO
     * URL: DELETE /api/products/{productId}/photos/{photoId}
     */
    public function deletePhoto(Request $request, $productId, $photoId)
    {
        $user = $request->user();
        $shop = $user->shop;
        $product = Product::where('shop_id', $shop->id)->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $photo = ProductPhoto::where('product_id', $product->id)->find($photoId);

        if (!$photo) {
            return response()->json([
                'success' => false,
                'message' => 'Photo non trouvée'
            ], 404);
        }

        $photo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Photo supprimée avec succès'
        ]);
    }

    /**
     * 8. PRODUITS SIMILAIRES (même catégorie)
     * URL: GET /api/products/{id}/similar
     */
    public function similar($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $similar = Product::with(['shop', 'photos'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $similar
        ]);
    }

    /**
     * 9. PRODUITS EN PROMOTION
     * URL: GET /api/products/promotions
     */
    public function promotedProducts(Request $request)
    {
        $query = Product::with(['shop', 'category', 'photos'])
            ->where('status', 'active')
            ->whereHas('activePromotion');

        $products = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * 10. EXPORTER LE CATALOGUE (CSV/Excel)
     * URL: GET /api/products/export
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        $products = Product::where('shop_id', $shop->id)->get();

        // À implémenter avec maatwebsite/excel
        return response()->json([
            'success' => true,
            'message' => 'Export en cours de développement',
            'total_products' => $products->count()
        ]);
    }

    /**
     * 11. IMPORTER LE CATALOGUE (CSV/Excel)
     * URL: POST /api/products/import
     */
    public function import(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        // À implémenter avec maatwebsite/excel
        return response()->json([
            'success' => true,
            'message' => 'Import en cours de développement'
        ]);
    }

    /**
     * Fonction utilitaire : générer un slug unique
     */
    private function generateSlug($name, $shopId)
    {
        $slug = Str::slug($name);
        $count = Product::where('slug', 'LIKE', "{$slug}%")
            ->where('shop_id', $shopId)
            ->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }
}
