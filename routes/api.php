<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\InterestController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PromotionController;
use App\Http\Controllers\API\AdController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\DeliveryController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\ChatController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\ReportController;

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

// Catégories (consultation publique)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/categories/tree', [CategoryController::class, 'tree']);
Route::get('/categories/{id}/products', [CategoryController::class, 'products']);

// Produits (consultation publique)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/similar', [ProductController::class, 'similar']);
Route::get('/products/promotions', [ProductController::class, 'promotedProducts']);

// Promotions (consultation publique)
Route::get('/promotions', [PromotionController::class, 'activePromotions']);
Route::get('/promotions/flash-sales', [PromotionController::class, 'flashSales']);
Route::get('/promotions/{id}', [PromotionController::class, 'show']);

// Publicités (consultation publique)
Route::get('/ads/banners', [AdController::class, 'activeBanners']);
Route::get('/ads/sponsored-products', [AdController::class, 'sponsoredProducts']);
Route::get('/ads/featured-shops', [AdController::class, 'featuredShops']);
Route::post('/ads/{id}/click', [AdController::class, 'trackClick']);
Route::post('/ads/{id}/impression', [AdController::class, 'trackImpression']);

// Santé API (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'MBOA SHOP API fonctionne',
        'version' => '1.0.0',
        'timestamp' => now()
    ]);
});

// ==========================================
// ROUTES PROTÉGÉES (token requis)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Profil utilisateur (tous rôles)
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);

    // Centres d'intérêt (tous rôles)
    Route::prefix('interests')->group(function () {
        Route::get('/', [InterestController::class, 'myInterests']);
        Route::post('/add', [InterestController::class, 'add']);
        Route::put('/update', [InterestController::class, 'update']);
        Route::delete('/remove/{categoryId}', [InterestController::class, 'remove']);
        Route::get('/recommendations', [InterestController::class, 'recommendations']);
    });

    // Notifications (tous rôles)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('/notification-settings', [NotificationController::class, 'updateSettings']);
    Route::post('/notifications/device-token', [NotificationController::class, 'registerDeviceToken']);

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

    // Avis et notations (tous rôles)
    Route::prefix('reviews')->group(function () {
        Route::post('/product/{productId}', [ReviewController::class, 'rateProduct']);
        Route::post('/shop/{shopId}', [ReviewController::class, 'rateShop']);
        Route::post('/driver/{driverId}', [ReviewController::class, 'rateDriver']);
        Route::get('/my', [ReviewController::class, 'myReviews']);
    });
});

// ==========================================
// ROUTES CLIENTS UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:client'])->group(function () {

    // Dashboard client
    Route::get('/client/dashboard', [DashboardController::class, 'clientDashboard']);

    // Panier
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::get('/count', [CartController::class, 'count']);
        Route::post('/add', [CartController::class, 'add']);
        Route::put('/update/{id}', [CartController::class, 'update']);
        Route::delete('/remove/{id}', [CartController::class, 'remove']);
        Route::delete('/clear', [CartController::class, 'clear']);
        Route::post('/sync', [CartController::class, 'sync']);
    });

    // Commandes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/', [OrderController::class, 'store']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/validate-reception', [OrderController::class, 'validateReception']);
        Route::post('/{id}/rate-delivery', [OrderController::class, 'rateDelivery']);
    });

    // Suivi livreur
    Route::get('/orders/{orderId}/driver-location', [DeliveryController::class, 'getDriverLocation']);
});

// ==========================================
// ROUTES COMMERCANTS ET GROSSISTES
// ==========================================
Route::middleware(['auth:sanctum', 'role:commercant,grossiste'])->group(function () {

    // Dashboard vendeur
    Route::get('/seller/dashboard', [DashboardController::class, 'sellerDashboard']);
    Route::get('/seller/stats', [DashboardController::class, 'sellerStats']);

    // Gestion boutique
    Route::prefix('shop')->group(function () {
        Route::post('/create', [ShopController::class, 'create']);
        Route::get('/my-shop', [ShopController::class, 'myShop']);
        Route::put('/update', [ShopController::class, 'update']);
        Route::get('/stats', [ShopController::class, 'stats']);
    });

    // Gestion produits
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{id}', [ProductController::class, 'update']);
        Route::delete('/{id}', [ProductController::class, 'destroy']);
        Route::post('/{id}/photos', [ProductController::class, 'addPhotos']);
        Route::delete('/{productId}/photos/{photoId}', [ProductController::class, 'deletePhoto']);
        Route::post('/{id}/photos/reorder', [ProductController::class, 'reorderPhotos']);
        Route::put('/{id}/photos/{photoId}/primary', [ProductController::class, 'setPrimaryPhoto']);
        Route::get('/export', [ProductController::class, 'export']);
        Route::post('/import', [ProductController::class, 'import']);
    });

    // Gestion promotions
    Route::prefix('promotions')->group(function () {
        Route::post('/', [PromotionController::class, 'create']);
        Route::put('/{id}', [PromotionController::class, 'update']);
        Route::delete('/{id}', [PromotionController::class, 'destroy']);
        Route::get('/my', [PromotionController::class, 'myPromotions']);
    });

    // Gestion réductions produits
    Route::post('/products/{id}/discount', [ProductController::class, 'addDiscount']);
    Route::delete('/products/{id}/discount', [ProductController::class, 'removeDiscount']);

    // Commandes reçues
    Route::get('/orders/received', [OrderController::class, 'receivedOrders']);
    Route::put('/orders/{id}/status', [OrderController::class, 'updateOrderStatus']);

    // Publicités (voir leurs campagnes)
    Route::get('/my-ads', [AdController::class, 'myCampaigns']);
});

// ==========================================
// ROUTES LIVREURS UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:livreur'])->group(function () {

    // Dashboard livreur
    Route::get('/driver/dashboard', [DashboardController::class, 'driverDashboard']);

    // Profil livreur
    Route::post('/driver/profile', [DeliveryController::class, 'completeProfile']);
    Route::put('/driver/status', [DeliveryController::class, 'updateStatus']);

    // Missions
    Route::get('/driver/missions', [DeliveryController::class, 'availableMissions']);
    Route::post('/driver/missions/{orderId}/accept', [DeliveryController::class, 'acceptMission']);
    Route::get('/driver/my-missions', [DeliveryController::class, 'myMissions']);
    Route::put('/driver/missions/{assignmentId}/status', [DeliveryController::class, 'updateMissionStatus']);

    // Localisation GPS
    Route::post('/driver/location', [DeliveryController::class, 'updateLocation']);

    // Validation PIN
    Route::post('/delivery/validate-pin', [OrderController::class, 'validatePin']);

    // Historique et gains
    Route::get('/driver/history', [DeliveryController::class, 'history']);
    Route::get('/driver/earnings', [DeliveryController::class, 'earnings']);
});

// ==========================================
// ROUTES ADMIN UNIQUEMENT
// ==========================================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/stats', [AdminController::class, 'stats']);

    // Utilisateurs
    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}/role', [AdminController::class, 'updateUserRole']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

    // Vendeurs (commerçants et grossistes)
    Route::get('/sellers', [AdminController::class, 'sellers']);
    Route::get('/sellers/{id}/stats', [AdminController::class, 'sellerStats']);

    // Boutiques
    Route::get('/shops/pending', [AdminController::class, 'pendingShops']);
    Route::put('/shops/{id}/validate', [AdminController::class, 'validateShop']);
    Route::put('/shops/{id}/reject', [AdminController::class, 'rejectShop']);

    // Livreurs
    Route::get('/drivers', [AdminController::class, 'drivers']);
    Route::put('/drivers/{id}/verify', [AdminController::class, 'verifyDriver']);
    Route::put('/drivers/{id}/block', [AdminController::class, 'blockDriver']);

    // Commandes
    Route::get('/orders', [OrderController::class, 'allOrders']);
    Route::put('/orders/{id}/assign-driver', [OrderController::class, 'assignDriver']);
    Route::put('/orders/{id}/status', [OrderController::class, 'adminUpdateOrderStatus']);
    Route::get('/orders/{id}/delivery-details', [OrderController::class, 'deliveryDetails']);

    // Produits (modération)
    Route::get('/products', [AdminController::class, 'products']);
    Route::put('/products/{id}/moderate', [AdminController::class, 'moderateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);

    // Catégories (CRUD complet)
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Promotions (validation)
    Route::get('/promotions/pending', [PromotionController::class, 'pendingPromotions']);
    Route::put('/promotions/{id}/validate', [PromotionController::class, 'validatePromotion']);

    // Publicités
    Route::prefix('ads')->group(function () {
        Route::post('/campaigns', [AdController::class, 'createCampaign']);
        Route::get('/campaigns', [AdController::class, 'allCampaigns']);
        Route::put('/campaigns/{id}', [AdController::class, 'updateCampaign']);
        Route::delete('/campaigns/{id}', [AdController::class, 'deleteCampaign']);
        Route::put('/campaigns/{id}/validate', [AdController::class, 'validateCampaign']);
        Route::get('/campaigns/{id}/stats', [AdController::class, 'campaignStats']);
    });

    // Commissions
    Route::put('/commission', [AdminController::class, 'updateCommission']);
    Route::get('/commission', [AdminController::class, 'getCommission']);
    Route::get('/commission/sellers', [AdminController::class, 'sellerCommission']);

    // Abonnements premium
    Route::get('/subscriptions', [AdminController::class, 'subscriptions']);
    Route::put('/subscriptions/plans', [AdminController::class, 'updateSubscriptionPlans']);

    // Zones de livraison
    Route::post('/delivery-zones', [DeliveryController::class, 'createZone']);
    Route::get('/delivery-zones', [DeliveryController::class, 'listZones']);
    Route::put('/delivery-zones/{id}', [DeliveryController::class, 'updateZone']);
    Route::delete('/delivery-zones/{id}', [DeliveryController::class, 'deleteZone']);

    // Supervisions (chats)
    Route::get('/conversations', [AdminController::class, 'allConversations']);
    Route::get('/conversations/{id}/messages', [AdminController::class, 'viewConversation']);

    // Rapports et exports
    Route::get('/reports/financial', [AdminController::class, 'financialReport']);
    Route::get('/reports/orders', [ReportController::class, 'ordersReport']);
    Route::get('/export/orders', [ReportController::class, 'exportOrders']);
    Route::get('/export/users', [ReportController::class, 'exportUsers']);
    Route::get('/export/products', [ReportController::class, 'exportProducts']);

    // Logs système
    Route::get('/logs', [AdminController::class, 'logs']);
    Route::get('/health', [AdminController::class, 'healthCheck']);
});

// ==========================================
// ROUTES DE TEST (à retirer en production)
// ==========================================
if (app()->environment('local')) {
    Route::get('/test/hello', function () {
        return response()->json(['message' => 'MBOA SHOP API - Test réussi!']);
    });
}
