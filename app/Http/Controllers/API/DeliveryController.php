<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\DriverAssignment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    // ==========================================
    // ROUTES LIVREURS (authentifiés)
    // ==========================================

    /**
     * 1. COMPLÉTER LE PROFIL LIVREUR (après inscription)
     * URL: POST /api/driver/profile
     * Body: vehicle_type, license_plate, id_card
     */
    public function completeProfile(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'livreur') {
            return response()->json([
                'success' => false,
                'message' => 'Compte non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_type' => 'required|string|in:moto,velo,voiture',
            'license_plate' => 'nullable|string',
            'id_card' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $driver = Driver::updateOrCreate(
            ['user_id' => $user->id],
            [
                'vehicle_type' => $request->vehicle_type,
                'license_plate' => $request->license_plate,
                'id_card' => $request->id_card,
                'status' => 'pending'
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil complété. En attente de validation par l\'administrateur.',
            'data' => $driver
        ]);
    }

    /**
     * 2. VOIR LES MISSIONS DISPONIBLES
     * URL: GET /api/driver/missions
     */
    public function availableMissions(Request $request)
    {
        $user = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver || $driver->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte livreur n\'est pas actif'
            ], 403);
        }

        // Commandes assignées mais pas encore acceptées
        $missions = Order::with(['shop', 'user'])
            ->where('status', 'assigned')
            ->whereNull('driver_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $missions
        ]);
    }

    /**
     * 3. ACCEPTER UNE MISSION
     * URL: POST /api/driver/missions/{orderId}/accept
     */
    public function acceptMission(Request $request, $orderId)
    {
        $user = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();

        if (!$driver || $driver->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Compte livreur non actif'
            ], 403);
        }

        $order = Order::where('status', 'assigned')
            ->whereNull('driver_id')
            ->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Mission non disponible'
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Assigner le livreur à la commande
            $order->driver_id = $user->id;
            $order->status = 'delivering';
            $order->save();

            // Créer l'assignation
            $assignment = DriverAssignment::create([
                'order_id' => $order->id,
                'driver_id' => $user->id,
                'status' => 'accepted',
                'accepted_at' => now(),
                'delivery_fee' => 1500 // Frais de livraison fixe
            ]);

            // Ajouter historique
            $order->addStatusHistory('delivering', 'Livreur assigné: ' . $user->name, $user->id);

            DB::commit();

            // Notifier le client
            $this->notifyClientDriverAssigned($order);

            return response()->json([
                'success' => true,
                'message' => 'Mission acceptée',
                'data' => [
                    'order' => $order,
                    'assignment' => $assignment
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. METTRE À JOUR SA POSITION GPS
     * URL: POST /api/driver/location
     * Body: latitude, longitude, order_id (optionnel), speed, accuracy
     */
    public function updateLocation(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'order_id' => 'nullable|exists:orders,id',
            'speed' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DriverLocation::create([
            'driver_id' => $user->id,
            'order_id' => $request->order_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'speed' => $request->speed,
            'accuracy' => $request->accuracy,
            'recorded_at' => now(),
        ]);

        // Mettre à jour le statut du livreur si nécessaire
        if ($request->order_id) {
            $this->updateOrderTracking($request->order_id, $user->id, $request->latitude, $request->longitude);
        }

        return response()->json([
            'success' => true,
            'message' => 'Position mise à jour'
        ]);
    }

    /**
     * 5. VOIR LA POSITION D'UN LIVREUR (client)
     * URL: GET /api/orders/{orderId}/driver-location
     */
    public function getDriverLocation($orderId)
    {
        $order = Order::find($orderId);

        if (!$order || !$order->driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun livreur assigné'
            ], 404);
        }

        $lastLocation = DriverLocation::where('driver_id', $order->driver_id)
            ->where('order_id', $orderId)
            ->latest('recorded_at')
            ->first();

        if (!$lastLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Position non disponible'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => $lastLocation->latitude,
                'longitude' => $lastLocation->longitude,
                'last_update' => $lastLocation->recorded_at,
                'speed' => $lastLocation->speed,
            ]
        ]);
    }

    /**
     * 6. MES MISSIONS (livreur)
     * URL: GET /api/driver/my-missions
     */
    public function myMissions(Request $request)
    {
        $user = $request->user();

        $assignments = DriverAssignment::with(['order', 'order.shop', 'order.user'])
            ->where('driver_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * 7. METTRE À JOUR LE STATUT DE LA MISSION
     * URL: PUT /api/driver/missions/{assignmentId}/status
     * Body: status (picked_up, delivered)
     */
    public function updateMissionStatus(Request $request, $assignmentId)
    {
        $user = $request->user();

        $assignment = DriverAssignment::where('driver_id', $user->id)
            ->with('order')
            ->find($assignmentId);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Mission non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:picked_up,delivered'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->status === 'picked_up') {
                $assignment->status = 'picked_up';
                $assignment->picked_up_at = now();
                $assignment->save();

                $assignment->order->addStatusHistory('picked_up', 'Colis récupéré par le livreur', $user->id);

            } elseif ($request->status === 'delivered') {
                // La livraison ne sera complète qu'avec le PIN
                // On attend la validation du client et du livreur via OrderController
                $assignment->status = 'delivered';
                $assignment->delivered_at = now();
                $assignment->save();

                // Mettre à jour les stats du livreur
                $driver = Driver::where('user_id', $user->id)->first();
                if ($driver) {
                    $driver->increment('total_deliveries');
                    $driver->increment('total_earnings', $assignment->delivery_fee);
                    $driver->increment('current_balance', $assignment->delivery_fee);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'data' => $assignment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 8. CHANGER LE STATUT EN LIGNE/HORS LIGNE
     * URL: PUT /api/driver/status
     * Body: is_online (boolean)
     */
    public function updateStatus(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'is_online' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $driver = Driver::where('user_id', $user->id)->first();

        if ($driver) {
            $driver->is_online = $request->is_online;
            $driver->save();
        }

        return response()->json([
            'success' => true,
            'message' => $request->is_online ? 'Vous êtes maintenant en ligne' : 'Vous êtes hors ligne'
        ]);
    }

    /**
     * 9. HISTORIQUE DES LIVRAISONS (livreur)
     * URL: GET /api/driver/history
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $deliveries = DriverAssignment::with(['order', 'order.shop'])
            ->where('driver_id', $user->id)
            ->where('status', 'delivered')
            ->orderBy('delivered_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $deliveries
        ]);
    }

    /**
     * 10. GAINS (livreur)
     * URL: GET /api/driver/earnings
     */
    public function earnings(Request $request)
    {
        $user = $request->user();

        $driver = Driver::where('user_id', $user->id)->first();

        $stats = [
            'total_deliveries' => $driver->total_deliveries ?? 0,
            'total_earnings' => $driver->total_earnings ?? 0,
            'current_balance' => $driver->current_balance ?? 0,
        ];

        // Ajouter les gains par mois
        $monthlyEarnings = DriverAssignment::select(
                DB::raw('DATE_FORMAT(delivered_at, "%Y-%m") as month'),
                DB::raw('SUM(delivery_fee) as total')
            )
            ->where('driver_id', $user->id)
            ->where('status', 'delivered')
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        $stats['monthly_earnings'] = $monthlyEarnings;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    // ==========================================
    // FONCTIONS UTILITAIRES
    // ==========================================

    private function updateOrderTracking($orderId, $driverId, $latitude, $longitude)
    {
        // Mettre à jour la position dans la table orders (optionnel)
        \Log::info("Order {$orderId} - Driver {$driverId} at {$latitude}, {$longitude}");
    }

    private function notifyClientDriverAssigned($order)
    {
        // À implémenter avec les notifications
        \Log::info("Notification: Livreur assigné pour commande {$order->order_number}");
    }
}
