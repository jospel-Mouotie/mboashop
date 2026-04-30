<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * DASHBOARD CLIENT
     * URL: GET /api/client/dashboard
     */
    public function clientDashboard(Request $request)
    {
        $user = $request->user();

        // Commandes récentes
        $recentOrders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Statistiques commandes
        $orderStats = [
            'total' => Order::where('user_id', $user->id)->count(),
            'pending' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
            'delivered' => Order::where('user_id', $user->id)->where('status', 'delivered')->count(),
            'cancelled' => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
        ];

        // Dépenses totales
        $totalSpent = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->sum('total_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                ],
                'order_stats' => $orderStats,
                'total_spent' => $totalSpent,
                'recent_orders' => $recentOrders,
            ]
        ]);
    }

    /**
     * DASHBOARD VENDEUR (commerçant/grossiste)
     * URL: GET /api/seller/dashboard
     */
    public function sellerDashboard(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        // Statistiques produits
        $productStats = [
            'total' => Product::where('shop_id', $shop->id)->count(),
            'active' => Product::where('shop_id', $shop->id)->where('status', 'active')->count(),
            'out_of_stock' => Product::where('shop_id', $shop->id)->where('stock', 0)->count(),
        ];

        // Commandes
        $recentOrders = Order::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $orderStats = [
            'total' => Order::where('shop_id', $shop->id)->count(),
            'pending' => Order::where('shop_id', $shop->id)->where('status', 'pending')->count(),
            'delivered' => Order::where('shop_id', $shop->id)->where('status', 'delivered')->count(),
            'total_revenue' => Order::where('shop_id', $shop->id)
                ->where('status', 'delivered')
                ->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'shop' => $shop,
                'product_stats' => $productStats,
                'order_stats' => $orderStats,
                'recent_orders' => $recentOrders,
            ]
        ]);
    }

    /**
     * STATISTIQUES VENDEUR (détaillées)
     * URL: GET /api/seller/stats
     */
    public function sellerStats(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        // Ventes par mois
        $salesByMonth = Order::where('shop_id', $shop->id)
            ->where('status', 'delivered')
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        // Meilleures ventes
        $topProducts = Order::join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.shop_id', $shop->id)
            ->where('orders.status', 'delivered')
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(order_items.quantity) as total_sold')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_sold', 'desc')
            ->limit(5)
            ->get();

        // Avis boutique
        $averageRating = $shop->rating ?? 0;
        $totalReviews = \App\Models\Review::where('shop_id', $shop->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'sales_by_month' => $salesByMonth,
                'top_products' => $topProducts,
                'rating' => [
                    'average' => $averageRating,
                    'total' => $totalReviews,
                ]
            ]
        ]);
    }

    /**
     * DASHBOARD LIVREUR
     * URL: GET /api/driver/dashboard
     */
    public function driverDashboard(Request $request)
    {
        $user = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Profil livreur non complété'
            ], 404);
        }

        // Missions en cours
        $currentMission = \App\Models\DriverAssignment::with('order')
            ->where('driver_id', $user->id)
            ->whereIn('status', ['accepted', 'picked_up'])
            ->first();

        // Statistiques
        $stats = [
            'total_deliveries' => $driver->total_deliveries,
            'total_earnings' => $driver->total_earnings,
            'current_balance' => $driver->current_balance,
            'rating' => $driver->rating,
            'is_online' => $driver->is_online,
            'status' => $driver->status,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'driver' => [
                    'vehicle_type' => $driver->vehicle_type,
                    'license_plate' => $driver->license_plate,
                ],
                'stats' => $stats,
                'current_mission' => $currentMission,
            ]
        ]);
    }
}
