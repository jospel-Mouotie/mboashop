<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * 1. LISTER MES NOTIFICATIONS
     * URL: GET /api/notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
                'notifications' => $notifications
            ]
        ]);
    }

    /**
     * 2. MARQUER UNE NOTIFICATION COMME LUE
     * URL: PUT /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $notification = Notification::where('user_id', $user->id)
            ->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée'
            ], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    /**
     * 3. MARQUER TOUTES LES NOTIFICATIONS COMME LUES
     * URL: POST /api/notifications/read-all
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues'
        ]);
    }

    /**
     * 4. PARAMÈTRES DE NOTIFICATION
     * URL: PUT /api/notification-settings
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'push_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'order_updates' => 'boolean',
            'promotions' => 'boolean',
            'chat_messages' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Stocker les paramètres (à créer table user_settings)
        \Log::info("Paramètres notification mis à jour pour user {$user->id}", $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Paramètres mis à jour'
        ]);
    }

    /**
     * 5. ENREGISTRER LE TOKEN FCM DE L'APPAREIL
     * URL: POST /api/notifications/device-token
     * Body: token, device_type (ios/android/web)
     */
    public function registerDeviceToken(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_type' => 'required|in:ios,android,web'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'token' => $request->token
            ],
            [
                'device_type' => $request->device_type,
                'is_active' => true
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Token enregistré avec succès'
        ]);
    }
}
