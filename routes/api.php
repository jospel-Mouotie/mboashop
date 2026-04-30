<?php

use App\Http\Controllers\API\AdController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\InterestController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;

// ==========================================
// ROUTES PUBLIQUES (aucun token requis)
// ==========================================

// Authentification
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Google OAuth
Route::get('/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Facebook OAuth
Route::get('/auth/facebook/redirect', [AuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

// Catégories (consultation)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/categories/tree', [CategoryController::class, 'tree']);
Route::get('/categories/{id}/products', [CategoryController::class, 'products']);

// Produits (consultation)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/similar', [ProductController::class, 'similar']);

// Promotions et offres spéciales (consultation publique)
Route::get('/promotions', [PromotionController::class, 'activePromotions']);
Route::get('/promotions/flash-sales', [PromotionController::class, 'flashSales']);
Route::get('/products/promotions', [ProductController::class, 'promotedProducts']);

// Publicités (bannières visibles par tous)
Route::get('/ads/banners', [AdController::class, 'activeBanners']);
Route::get('/ads/sponsored-products', [AdController::class, 'sponsoredProducts']);

// ==========================================
// ROUTES PROTÉGÉES (token requis)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Profil utilisateur
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);

    // Centres d'intérêt
    Route::prefix('interests')->group(function () {
        Route::get('/', [InterestController::class, 'myInterests']);
        Route::post('/add', [InterestController::class, 'add']);
        Route::put('/update', [InterestController::class, 'update']);
        Route::delete('/remove/{categoryId}', [InterestController::class, 'remove']);
        Route::get('/recommendations', [InterestController::class, 'recommendations']);
    });

// ROUTES PANIER (clients uniquement)
// ==========================================
Route::middleware(['auth:sanctum', 'role:client'])->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::get('/count', [CartController::class, 'count']);
    Route::post('/add', [CartController::class, 'add']);
    Route::put('/update/{id}', [CartController::class, 'update']);
    Route::delete('/remove/{id}', [CartController::class, 'remove']);
    Route::delete('/clear', [CartController::class, 'clear']);
    Route::post('/sync', [CartController::class, 'sync']);
});
    // Commandes (uniquement clients)
    Route::middleware(['role:client'])->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{id}/validate-reception', [OrderController::class, 'validateReception']);
        Route::post('/orders/{id}/rate-delivery', [OrderController::class, 'rateDelivery']);
    });

    // Notifications (tous utilisateurs)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('/notification-settings', [NotificationController::class, 'updateSettings']);
});

// ==========================================
// ROUTES CLIENTS UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {
    Route::get('/client/dashboard', [DashboardController::class, 'clientDashboard']);
    Route::get('/client/orders', [OrderController::class, 'clientOrders']);
    Route::get('/client/recommendations', [ProductController::class, 'personalizedRecommendations']);
});

// ==========================================
// ROUTES COMMERCANTS ET GROSSISTES
// ==========================================
Route::middleware(['auth:sanctum', 'role:commercant,grossiste'])->group(function () {

    // Gestion boutique
    Route::post('/shop/create', [ShopController::class, 'create']);
    Route::get('/my-shop', [ShopController::class, 'myShop']);
    Route::put('/shop/update', [ShopController::class, 'update']);
    Route::get('/shop/stats', [ShopController::class, 'stats']);

    // Gestion produits (CRUD complet)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/photos', [ProductController::class, 'addPhotos']);
    Route::delete('/products/{productId}/photos/{photoId}', [ProductController::class, 'deletePhoto']);

    // Gestion des images produits
    Route::post('/products/{id}/photos/reorder', [ProductController::class, 'reorderPhotos']);
    Route::put('/products/{id}/photos/{photoId}/primary', [ProductController::class, 'setPrimaryPhoto']);

    // Gestion des promotions (pour commerçants et grossistes)
    Route::post('/promotions', [PromotionController::class, 'create']);
    Route::put('/promotions/{id}', [PromotionController::class, 'update']);
    Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);
    Route::get('/my-promotions', [PromotionController::class, 'myPromotions']);

    // Gestion des réductions sur produits
    Route::post('/products/{id}/discount', [ProductController::class, 'addDiscount']);
    Route::delete('/products/{id}/discount', [ProductController::class, 'removeDiscount']);

    // Commandes reçues
    Route::get('/orders/received', [OrderController::class, 'receivedOrders']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateOrderStatus']);

    // Export catalogue
    Route::get('/products/export', [ProductController::class, 'export']);
    Route::post('/products/import', [ProductController::class, 'import']);

    // Statistiques boutique
    Route::get('/dashboard/stats', [DashboardController::class, 'sellerStats']);
});

// ==========================================
// ROUTES LIVREURS UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:livreur'])->group(function () {

    // Gestion des missions
    Route::get('/delivery/missions', [DeliveryController::class, 'availableMissions']);
    Route::post('/delivery/missions/{id}/accept', [DeliveryController::class, 'acceptMission']);
    Route::get('/delivery/my-missions', [DeliveryController::class, 'myMissions']);
    Route::put('/delivery/missions/{id}/status', [DeliveryController::class, 'updateMissionStatus']);

    // Suivi GPS
    Route::post('/delivery/location', [DeliveryController::class, 'updateLocation']);
    Route::get('/delivery/orders/{id}/tracking', [DeliveryController::class, 'getTracking']);

    // Historique et gains
    Route::get('/delivery/history', [DeliveryController::class, 'history']);
    Route::get('/delivery/earnings', [DeliveryController::class, 'earnings']);

    // Statut livreur
    Route::put('/delivery/status', [DeliveryController::class, 'updateStatus']);
});

// ==========================================
// ROUTES ADMIN UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {

    // Dashboard Admin
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/revenue', [AdminController::class, 'revenue']);

    // Gestion utilisateurs
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::put('/admin/users/{id}/role', [AdminController::class, 'updateUserRole']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);

    // Validation des boutiques
    Route::get('/admin/shops/pending', [AdminController::class, 'pendingShops']);
    Route::put('/admin/shops/{id}/validate', [AdminController::class, 'validateShop']);
    Route::put('/admin/shops/{id}/reject', [AdminController::class, 'rejectShop']);

    // Gestion des commerçants/grossistes
    Route::get('/admin/sellers', [AdminController::class, 'sellers']);
    Route::get('/admin/sellers/{id}/stats', [AdminController::class, 'sellerStats']);

    // Gestion des livreurs
    Route::get('/admin/drivers', [AdminController::class, 'drivers']);
    Route::put('/admin/drivers/{id}/verify', [AdminController::class, 'verifyDriver']);
    Route::put('/admin/drivers/{id}/block', [AdminController::class, 'blockDriver']);

    // Gestion des produits (modération)
    Route::get('/admin/products', [AdminController::class, 'products']);
    Route::put('/admin/products/{id}/moderate', [AdminController::class, 'moderateProduct']);
    Route::delete('/admin/products/{id}', [AdminController::class, 'deleteProduct']);

    // Gestion des catégories (CRUD complet)
    Route::post('/admin/categories', [CategoryController::class, 'store']);
    Route::put('/admin/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/admin/categories/{id}', [CategoryController::class, 'destroy']);

    // Gestion des promotions (validation)
    Route::get('/admin/promotions/pending', [AdminController::class, 'pendingPromotions']);
    Route::put('/admin/promotions/{id}/validate', [AdminController::class, 'validatePromotion']);

    // Gestion des publicités
    Route::post('/admin/ads/banners', [AdController::class, 'createBanner']);
    Route::put('/admin/ads/banners/{id}', [AdController::class, 'updateBanner']);
    Route::delete('/admin/ads/banners/{id}', [AdController::class, 'deleteBanner']);
    Route::post('/admin/ads/sponsored', [AdController::class, 'createSponsoredProduct']);

    // Commission globale
    Route::put('/admin/commission', [AdminController::class, 'updateCommission']);
    Route::get('/admin/commission', [AdminController::class, 'getCommission']);

    // Abonnements premium
    Route::get('/admin/subscriptions', [AdminController::class, 'subscriptions']);
    Route::put('/admin/subscriptions/plans', [AdminController::class, 'updateSubscriptionPlans']);

    // Zones de livraison
    Route::post('/admin/delivery-zones', [DeliveryController::class, 'createZone']);
    Route::put('/admin/delivery-zones/{id}', [DeliveryController::class, 'updateZone']);
    Route::delete('/admin/delivery-zones/{id}', [DeliveryController::class, 'deleteZone']);

    // Rapports et exports
    Route::get('/admin/reports/orders', [ReportController::class, 'ordersReport']);
    Route::get('/admin/reports/financial', [ReportController::class, 'financialReport']);
    Route::get('/admin/export/orders', [ReportController::class, 'exportOrders']);
    Route::get('/admin/export/users', [ReportController::class, 'exportUsers']);

    // Logs système
    Route::get('/admin/logs', [AdminController::class, 'logs']);
    Route::get('/admin/health', [AdminController::class, 'healthCheck']);

    // Toutes les conversations (supervision)
    Route::get('/admin/conversations', [ChatController::class, 'allConversations']);
    Route::get('/admin/conversations/{id}/messages', [ChatController::class, 'viewConversation']);
});

// ==========================================
// ROUTES POUR TOUS LES UTILISATEURS CONNECTÉS
// (client, commerçant, grossiste, livreur)
// ==========================================
Route::middleware(['auth:sanctum'])->group(function () {

    // Chat (client ↔ livreur uniquement)
    Route::prefix('chat')->group(function () {
        Route::get('/conversations', [ChatController::class, 'conversations']);
        Route::post('/conversations/{orderId}', [ChatController::class, 'startConversation']);
        Route::get('/conversations/{id}/messages', [ChatController::class, 'messages']);
        Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::put('/messages/{id}/read', [ChatController::class, 'markAsRead']);
        Route::post('/conversations/{id}/close', [ChatController::class, 'closeConversation']);
        Route::post('/messages/{id}/report', [ChatController::class, 'reportMessage']);
    });

    // Avis et notations
    Route::post('/reviews/product/{productId}', [ReviewController::class, 'rateProduct']);
    Route::post('/reviews/shop/{shopId}', [ReviewController::class, 'rateShop']);
    Route::post('/reviews/driver/{driverId}', [ReviewController::class, 'rateDriver']);
    Route::get('/reviews/my', [ReviewController::class, 'myReviews']);
});

// ==========================================
// ROUTES DE TEST (à retirer en production)
// ==========================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'MBOA SHOP API fonctionne',
        'timestamp' => now()
    ]);
});

// ==========================================
// ROUTES PUBLICITÉS (publiques)
// ==========================================
Route::get('/ads/banners', [AdController::class, 'activeBanners']);
Route::get('/ads/sponsored-products', [AdController::class, 'sponsoredProducts']);
Route::get('/ads/featured-shops', [AdController::class, 'featuredShops']);
Route::post('/ads/{id}/click', [AdController::class, 'trackClick']);
Route::post('/ads/{id}/impression', [AdController::class, 'trackImpression']);

// ==========================================
// ROUTES PUBLICITÉS (admin uniquement)
// ==========================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/ads')->group(function () {
    Route::post('/campaigns', [AdController::class, 'createCampaign']);
    Route::get('/campaigns', [AdController::class, 'allCampaigns']);
    Route::put('/campaigns/{id}', [AdController::class, 'updateCampaign']);
    Route::delete('/campaigns/{id}', [AdController::class, 'deleteCampaign']);
    Route::put('/campaigns/{id}/validate', [AdController::class, 'validateCampaign']);
    Route::get('/campaigns/{id}/stats', [AdController::class, 'campaignStats']);
});

// ==========================================
// ROUTES PUBLICITÉS (commerçants)
// ==========================================
Route::middleware(['auth:sanctum', 'role:commercant,grossiste'])->group(function () {
    Route::get('/my-ads', [AdController::class, 'myCampaigns']);
});
