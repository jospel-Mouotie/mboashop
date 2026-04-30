<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * RAPPORT COMMANDES
     * URL: GET /api/admin/reports/orders
     */
    public function ordersReport(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth());
        $endDate = $request->get('end_date', now());

        $orders = Order::with(['shop', 'user', 'driver'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'total_orders' => $orders->count(),
            'total_revenue' => $orders->where('status', 'delivered')->sum('total_amount'),
            'total_commission' => $orders->where('status', 'delivered')->sum('total_amount') * 0.1,
            'delivered' => $orders->where('status', 'delivered')->count(),
            'pending' => $orders->where('status', 'pending')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => $summary,
                'orders' => $orders
            ]
        ]);
    }

    /**
     * EXPORT COMMANDES (CSV)
     * URL: GET /api/admin/export/orders
     */
    public function exportOrders(Request $request)
    {
        // À implémenter avec maatwebsite/excel
        return response()->json([
            'success' => true,
            'message' => 'Export en cours de développement'
        ]);
    }

    /**
     * EXPORT UTILISATEURS
     * URL: GET /api/admin/export/users
     */
    public function exportUsers(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Export en cours de développement'
        ]);
    }

    /**
     * EXPORT PRODUITS
     * URL: GET /api/admin/export/products
     */
    public function exportProducts(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Export en cours de développement'
        ]);
    }
}
