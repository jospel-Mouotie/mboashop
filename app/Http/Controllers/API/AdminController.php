<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use App\Models\Order;
use App\Models\Driver;
use App\Models\Product;
use App\Models\Conversation;
use App\Models\Promotion;
use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ==========================================
    // DASHBOARD & STATISTIQUES
    // ==========================================

    /**
     * 1. DASHBOARD ADMIN (statistiques globales)
     * URL: GET /api/admin/dashboard
     */
    public function dashboard()
    {
        // Statistiques utilisateurs
        $totalUsers = User::count();
        $totalClients = User::where('role', 'client')->count();
        $totalSellers = User::whereIn('role', ['commercant', 'grossiste'])->count();
        $totalDrivers = User::where('role', 'livreur')->count();

        // Statistiques boutiques
        $totalShops = Shop::count();
        $pendingShops = Shop::where('status', 'pending')->count();
        $activeShops = Shop::where('status', 'active')->count();

        // Statistiques commandes
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        // Chiffre d'affaires
        $totalRevenue = Order::where('status', 'delivered')->sum('total_amount');
        $todayRevenue = Order::where('status', 'delivered')
            ->whereDate('created_at', today())
            ->sum('total_amount');
        $monthRevenue = Order::where('status', 'delivered')
            ->whereMonth('created_at', now()->month)
            ->sum('total_amount');

        // Commission totale (10% par défaut)
        $commissionRate = config('app.commission_rate', 10);
        $totalCommission = $totalRevenue * $commissionRate / 100;

        // Produits
        $totalProducts = Product::count();
        $outOfStockProducts = Product::where('stock', 0)->count();

        // Livreurs actifs
        $activeDrivers = Driver::where('is_online', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'users' => [
                    'total' => $totalUsers,
                    'clients' => $totalClients,
                    'sellers' => $totalSellers,
                    'drivers' => $totalDrivers,
                ],
                'shops' => [
                    'total' => $totalShops,
                    'pending' => $pendingShops,
                    'active' => $activeShops,
                ],
                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'delivered' => $deliveredOrders,
                    'cancelled' => $cancelledOrders,
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                    'today' => $todayRevenue,
                    'month' => $monthRevenue,
                    'commission_rate' => $commissionRate,
                    'total_commission' => $totalCommission,
                ],
                'products' => [
                    'total' => $totalProducts,
                    'out_of_stock' => $outOfStockProducts,
                ],
                'drivers_active' => $activeDrivers,
            ]
        ]);
    }

    /**
     * 2. STATISTIQUES DÉTAILLÉES (graphiques)
     * URL: GET /api/admin/stats
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'month'); // week, month, year

        switch ($period) {
            case 'week':
                $startDate = now()->subDays(7);
                $groupBy = 'day';
                break;
            case 'year':
                $startDate = now()->subMonths(12);
                $groupBy = 'month';
                break;
            default: // month
                $startDate = now()->subDays(30);
                $groupBy = 'day';
        }

        // Commandes par jour/mois
        $ordersStats = Order::where('created_at', '>=', $startDate)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Nouveaux utilisateurs
        $usersStats = User::where('created_at', '>=', $startDate)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Top produits
        $topProducts = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price_at_time) as total_revenue')
            )
            ->where('orders.status', 'delivered')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'orders' => $ordersStats,
                'new_users' => $usersStats,
                'top_products' => $topProducts,
            ]
        ]);
    }

    // ==========================================
    // GESTION DES UTILISATEURS
    // ==========================================

    /**
     * 3. LISTER TOUS LES UTILISATEURS
     * URL: GET /api/admin/users
     */
    public function users(Request $request)
    {
        $users = User::with('shop')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * 4. MODIFIER LE RÔLE D'UN UTILISATEUR
     * URL: PUT /api/admin/users/{id}/role
     */
    public function updateUserRole(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $request->validate([
            'role' => 'required|in:client,commercant,grossiste,livreur,admin'
        ]);

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour',
            'data' => $user
        ]);
    }

    /**
     * 5. SUPPRIMER UN UTILISATEUR
     * URL: DELETE /api/admin/users/{id}
     */
    public function deleteUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé'
        ]);
    }

    // ==========================================
    // GESTION DES BOUTIQUES (validation)
    // ==========================================

    /**
     * 6. BOUTIQUES EN ATTENTE DE VALIDATION
     * URL: GET /api/admin/shops/pending
     */
    public function pendingShops()
    {
        $shops = Shop::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shops
        ]);
    }

    /**
     * 7. VALIDER UNE BOUTIQUE
     * URL: PUT /api/admin/shops/{id}/validate
     */
    public function validateShop($id)
    {
        $shop = Shop::find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $shop->status = 'active';
        $shop->save();

        // Activer tous les produits de la boutique
        Product::where('shop_id', $shop->id)->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Boutique validée avec succès'
        ]);
    }

    /**
     * 8. REFUSER UNE BOUTIQUE
     * URL: PUT /api/admin/shops/{id}/reject
     */
    public function rejectShop(Request $request, $id)
    {
        $shop = Shop::find($id);

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $shop->status = 'rejected';
        $shop->save();

        return response()->json([
            'success' => true,
            'message' => 'Boutique refusée'
        ]);
    }

    // ==========================================
    // GESTION DES LIVREURS
    // ==========================================

    /**
     * 9. LISTER TOUS LES LIVREURS
     * URL: GET /api/admin/drivers
     */
    public function drivers(Request $request)
    {
        $drivers = Driver::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $drivers
        ]);
    }

    /**
     * 10. VÉRIFIER UN LIVREUR
     * URL: PUT /api/admin/drivers/{id}/verify
     */
    public function verifyDriver($id)
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur non trouvé'
            ], 404);
        }

        $driver->status = 'active';
        $driver->save();

        return response()->json([
            'success' => true,
            'message' => 'Livreur vérifié et activé'
        ]);
    }

    /**
     * 11. BLOQUER UN LIVREUR
     * URL: PUT /api/admin/drivers/{id}/block
     */
    public function blockDriver($id)
    {
        $driver = Driver::find($id);

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur non trouvé'
            ], 404);
        }

        $driver->status = 'blocked';
        $driver->is_online = false;
        $driver->save();

        return response()->json([
            'success' => true,
            'message' => 'Livreur bloqué'
        ]);
    }

    // ==========================================
    // GESTION DES COMMISSIONS
    // ==========================================

    /**
     * 12. MODIFIER LE TAUX DE COMMISSION
     * URL: PUT /api/admin/commission
     * Body: rate (pourcentage)
     */
    public function updateCommission(Request $request)
    {
        $request->validate([
            'rate' => 'required|integer|min:0|max:100'
        ]);

        // Stocker dans config ou table settings
        config(['app.commission_rate' => $request->rate]);

        // Option: sauvegarder dans la base de données
        \App\Models\Setting::updateOrCreate(
            ['key' => 'commission_rate'],
            ['value' => $request->rate]
        );

        return response()->json([
            'success' => true,
            'message' => 'Taux de commission mis à jour',
            'commission_rate' => $request->rate
        ]);
    }

    /**
     * 13. COMMISSION PAR VENDEUR
     * URL: GET /api/admin/commission/sellers
     */
    public function sellerCommission(Request $request)
    {
        $sellerCommissions = Order::where('status', 'delivered')
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->join('users', 'shops.user_id', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('SUM(orders.total_amount) as total_sales'),
                DB::raw('SUM(orders.total_amount * ' . (config('app.commission_rate', 10) / 100) . ') as commission')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_sales', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sellerCommissions
        ]);
    }

    // ==========================================
    // GESTION DES PRODUITS (modération)
    // ==========================================

    /**
     * 14. SIGNALER UN PRODUIT (admin modère)
     * URL: PUT /api/admin/products/{id}/moderate
     */
    public function moderateProduct(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $request->validate([
            'action' => 'required|in:activate,deactivate,delete'
        ]);

        if ($request->action === 'delete') {
            $product->delete();
            $message = 'Produit supprimé';
        } else {
            $product->status = $request->action === 'activate' ? 'active' : 'inactive';
            $product->save();
            $message = 'Produit ' . ($request->action === 'activate' ? 'activé' : 'désactivé');
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    // ==========================================
    // SUPERVISION DES CHATS (admin voit tout)
    // ==========================================

    /**
     * 15. TOUTES LES CONVERSATIONS
     * URL: GET /api/admin/conversations
     */
    public function allConversations(Request $request)
    {
        $conversations = Conversation::with(['order', 'client', 'driver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * 16. VOIR UNE CONVERSATION SPÉCIFIQUE
     * URL: GET /api/admin/conversations/{id}/messages
     */
    public function viewConversation($id)
    {
        $conversation = Conversation::with(['messages', 'order', 'client', 'driver'])
            ->find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $conversation
        ]);
    }

    // ==========================================
    // RAPPORTS ET EXPORTS
    // ==========================================

    /**
     * 17. RAPPORT FINANCIER
     * URL: GET /api/admin/reports/financial
     */
    public function financialReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now());

        $stats = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'orders' => [
                'total' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
                'delivered' => Order::where('status', 'delivered')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
                'cancelled' => Order::where('status', 'cancelled')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
            'revenue' => [
                'total' => Order::where('status', 'delivered')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('total_amount'),
                'commission' => Order::where('status', 'delivered')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('total_amount') * (config('app.commission_rate', 10) / 100),
            ],
            'new_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // ==========================================
    // LOGS ET MONITORING
    // ==========================================

    /**
     * 18. LOGS ADMIN
     * URL: GET /api/admin/logs
     */
    public function logs(Request $request)
    {
        // À implémenter avec une table admin_logs
        return response()->json([
            'success' => true,
            'message' => 'Fonctionnalité en développement',
            'logs' => []
        ]);
    }

    /**
     * 19. SANTÉ DU SERVEUR
     * URL: GET /api/admin/health
     */
    public function healthCheck()
    {
        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ]);
    }
}
