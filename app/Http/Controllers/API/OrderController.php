<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OrderController extends Controller
{
    /**
     * 1. PASSER UNE COMMANDE (client)
     * URL: POST /api/orders
     * Body: delivery_address, delivery_phone, notes
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est un client
        if ($user->role !== 'client') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les clients peuvent passer commande'
            ], 403);
        }

        // Récupérer le panier
        $cartItems = CartItem::with('product')->where('user_id', $user->id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre panier est vide'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'delivery_address' => 'required|string',
            'delivery_phone' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Vérifier que la boutique est la même pour tous les produits
        $shopId = $cartItems->first()->product->shop_id;
        foreach ($cartItems as $item) {
            if ($item->product->shop_id != $shopId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez commander que des produits d\'une seule boutique à la fois'
                ], 400);
            }
        }

        // Calculer les totaux
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $price = $item->price_at_add;
            $subtotal += $price * $item->quantity;

            // Vérifier le stock
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock insuffisant pour {$item->product->name}"
                ], 400);
            }
        }

        $shippingCost = $subtotal >= 50000 ? 0 : 1500;
        $total = $subtotal + $shippingCost;

        DB::beginTransaction();

        try {
            // Créer la commande
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'driver_id' => null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'total_amount' => $total,
                'delivery_address' => $request->delivery_address,
                'delivery_phone' => $request->delivery_phone,
                'status' => 'pending',
                'notes' => $request->notes,
            ]);

            // Ajouter les articles
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price_at_time' => $item->price_at_add,
                    'options' => $item->options,
                ]);

                // Mettre à jour le stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Créer la livraison avec PIN
            $pin = Delivery::generatePin();
            $pinExpiresAt = now()->addHours(48); // 48h pour valider

            Delivery::create([
                'order_id' => $order->id,
                'pin' => bcrypt($pin), // Stocker hashé pour sécurité
                'pin_expires_at' => $pinExpiresAt,
                'pin_attempts' => 0,
                'client_validated' => false,
                'driver_validated' => false,
            ]);

            // Ajouter historique
            $order->addStatusHistory('pending', 'Commande créée', $user->id);

            // Vider le panier
            CartItem::where('user_id', $user->id)->delete();

            DB::commit();

            // Envoyer la notification PUSH avec le PIN au client
            $this->sendPinToClient($order, $pin);

            // Notifier l'admin (toi)
            $this->notifyAdmin($order);

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => [
                    'order' => $order,
                    'pin' => $pin, // À enlever en production, garder pour test
                    'pin_expires_at' => $pinExpiresAt
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2. LISTER MES COMMANDES (client)
     * URL: GET /api/orders
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['shop', 'items.product', 'delivery'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 3. VOIR UNE COMMANDE SPÉCIFIQUE
     * URL: GET /api/orders/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::with(['shop', 'items.product', 'items.product.photos', 'delivery', 'statusHistory'])
            ->where('user_id', $user->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * 4. CLIENT VALIDE LA RÉCEPTION (donne son code au livreur)
     * URL: POST /api/orders/{id}/validate-reception
     * Le client voit le code dans l'app et le donne au livreur
     */
    public function validateReception(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        $delivery = $order->delivery;

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: livraison non trouvée'
            ], 404);
        }

        if ($delivery->client_validated) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà validé cette livraison'
            ], 400);
        }

        if ($delivery->isPinExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Le code a expiré. Contactez l\'administrateur'
            ], 400);
        }

        // Le client valide
        $delivery->client_validated = true;
        $delivery->client_validated_at = now();
        $delivery->save();

        // Mettre à jour le statut de la commande
        if ($delivery->driver_validated) {
            $order->status = 'delivered';
            $order->addStatusHistory('delivered', 'Livraison complète - Client et livreur ont validé', $user->id);
        } else {
            $order->status = 'client_validated';
            $order->addStatusHistory('client_validated', 'Client a validé la réception, en attente validation livreur', $user->id);
        }
        $order->save();

        // Notifier le livreur que le client a validé
        if ($order->driver_id) {
            $this->notifyDriverClientValidated($order);
        }

        return response()->json([
            'success' => true,
            'message' => 'Validation client enregistrée. Le livreur doit maintenant entrer le code pour finaliser.'
        ]);
    }

    /**
     * 5. LIVREUR VALIDE LA COMMANDE (entre le code donné par le client)
     * URL: POST /api/delivery/validate-pin
     * Body: order_id, pin
     */
    public function validatePin(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'livreur') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les livreurs peuvent valider avec le code'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'pin' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($request->order_id);
        $delivery = $order->delivery;

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: livraison non trouvée'
            ], 404);
        }

        if ($delivery->driver_validated) {
            return response()->json([
                'success' => false,
                'message' => 'Code déjà utilisé'
            ], 400);
        }

        if ($delivery->isPinExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'Code expiré'
            ], 400);
        }

        // Vérifier le nombre de tentatives
        if ($delivery->pin_attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Contactez l\'administrateur'
            ], 400);
        }

        // Vérifier le PIN (hashé)
        if (!\Hash::check($request->pin, $delivery->pin)) {
            $delivery->increment('pin_attempts');
            return response()->json([
                'success' => false,
                'message' => 'Code PIN incorrect',
                'attempts_left' => 5 - $delivery->pin_attempts
            ], 400);
        }

        // PIN correct
        $delivery->driver_validated = true;
        $delivery->driver_validated_at = now();
        $delivery->save();

        // Mettre à jour le statut
        if ($delivery->client_validated) {
            $order->status = 'delivered';
            $order->addStatusHistory('delivered', 'Livraison complète - Client et livreur ont validé', $user->id);
        } else {
            $order->status = 'driver_validated';
            $order->addStatusHistory('driver_validated', 'Livreur a validé, en attente validation client', $user->id);
        }
        $order->save();

        // Notifier le client
        $this->notifyClientDriverValidated($order);

        return response()->json([
            'success' => true,
            'message' => 'Livraison validée avec succès'
        ]);
    }

    /**
     * 6. COMMERCANT/GROSSISTE VOIT SES COMMANDES
     * URL: GET /api/orders/received
     */
    public function receivedOrders(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop;

        if (!$shop) {
            return response()->json([
                'success' => false,
                'message' => 'Boutique non trouvée'
            ], 404);
        }

        $orders = Order::with(['user', 'items.product', 'delivery'])
            ->where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 7. ADMIN VOIT TOUTES LES COMMANDES
     * URL: GET /api/admin/orders
     */
    public function allOrders(Request $request)
    {
        $orders = Order::with(['user', 'shop', 'driver', 'delivery'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * 8. ADMIN ASSIGNE UN LIVREUR
     * URL: PUT /api/admin/orders/{id}/assign-driver
     */
    public function assignDriver(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'driver_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);
        $driver = User::where('role', 'livreur')->find($request->driver_id);

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur non trouvé'
            ], 404);
        }

        $order->driver_id = $request->driver_id;
        $order->status = 'assigned';
        $order->save();

        $order->addStatusHistory('assigned', "Livreur {$driver->name} assigné", $request->user()->id);

        // Notifier le livreur
        $this->notifyDriverNewAssignment($order, $driver);

        return response()->json([
            'success' => true,
            'message' => 'Livreur assigné avec succès'
        ]);
    }

    /**
     * 9. NOTER LE LIVREUR (client)
     * URL: POST /api/orders/{id}/rate-delivery
     * Body: rating (1-5), comment
     */
    public function rateDelivery(Request $request, $id)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if ($order->status !== 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez noter qu\'après livraison'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer l'avis (à implémenter dans ReviewController)
        // Pour l'instant, on stocke juste dans l'ordre
        $order->driver_rating = $request->rating;
        $order->driver_comment = $request->comment;
        $order->save();

        // Mettre à jour la note moyenne du livreur
        $driver = User::find($order->driver_id);
        if ($driver) {
            $avgRating = Order::where('driver_id', $driver->id)
                ->whereNotNull('driver_rating')
                ->avg('driver_rating');
            $driver->rating = round($avgRating, 1);
            $driver->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre évaluation !'
        ]);
    }

    // ==========================================
    // FONCTIONS DE NOTIFICATION (à implémenter)
    // ==========================================

    private function sendPinToClient($order, $pin)
    {
        // À implémenter avec Firebase + Reverb
        // Envoie le PIN au client par notification push
        \Log::info("PIN pour commande {$order->order_number}: {$pin}");
    }

    private function notifyAdmin($order)
    {
        // À implémenter - notification à l'admin
    }

    private function notifyDriverNewAssignment($order, $driver)
    {
        // À implémenter
    }

    private function notifyDriverClientValidated($order)
    {
        // À implémenter
    }

    private function notifyClientDriverValidated($order)
    {
        // À implémenter
    }
}
