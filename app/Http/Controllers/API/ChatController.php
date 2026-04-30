<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * 1. LISTER MES CONVERSATIONS
     * URL: GET /api/chat/conversations
     */
    public function conversations(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'client') {
            $conversations = Conversation::with(['order', 'driver'])
                ->where('client_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->get();
        } elseif ($user->role === 'livreur') {
            $conversations = Conversation::with(['order', 'client'])
                ->where('driver_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->get();
        } elseif ($user->role === 'admin') {
            $conversations = Conversation::with(['order', 'client', 'driver'])
                ->orderBy('updated_at', 'desc')
                ->get();
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * 2. DÉMARRER UNE CONVERSATION (pour une commande)
     * URL: POST /api/chat/conversations/{orderId}
     */
    public function startConversation(Request $request, $orderId)
    {
        $user = $request->user();

        $order = Order::with(['user', 'driver'])->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Seul le client ou le livreur peuvent démarrer une conversation
        if ($user->role === 'client' && $order->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas le propriétaire de cette commande'
            ], 403);
        }

        if ($user->role === 'livreur' && $order->driver_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas le livreur de cette commande'
            ], 403);
        }

        // Vérifier si une conversation existe déjà
        $conversation = Conversation::where('order_id', $orderId)->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'order_id' => $orderId,
                'client_id' => $order->user_id,
                'driver_id' => $order->driver_id,
                'status' => 'active'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $conversation
        ]);
    }

    /**
     * 3. VOIR LES MESSAGES D'UNE CONVERSATION
     * URL: GET /api/chat/conversations/{id}/messages
     */
    public function messages(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation non trouvée'
            ], 404);
        }

        // Vérifier les droits d'accès
        if ($user->role !== 'admin') {
            if ($user->role === 'client' && $conversation->client_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            if ($user->role === 'livreur' && $conversation->driver_id != $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }
        }

        $messages = Message::where('conversation_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Marquer les messages non lus comme lus
        if ($user->role !== 'admin') {
            Message::where('conversation_id', $id)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * 4. ENVOYER UN MESSAGE
     * URL: POST /api/chat/conversations/{id}/messages
     * Body: content, type (text/image)
     */
    public function sendMessage(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation non trouvée'
            ], 404);
        }

        // Vérifier les droits
        if ($user->role === 'client' && $conversation->client_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        if ($user->role === 'livreur' && $conversation->driver_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'type' => 'sometimes|in:text,image'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Déterminer le destinataire
        $receiverId = ($user->id === $conversation->client_id)
            ? $conversation->driver_id
            : $conversation->client_id;

        $message = Message::create([
            'conversation_id' => $id,
            'sender_id' => $user->id,
            'receiver_id' => $receiverId,
            'content' => $request->content,
            'type' => $request->type ?? 'text',
            'is_read' => false
        ]);

        // Mettre à jour la conversation
        $conversation->touch(); // updated_at

        // Envoyer une notification push au destinataire
        $this->sendPushNotification($receiverId, $message, $conversation);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé',
            'data' => $message
        ], 201);
    }

    /**
     * 5. MARQUER UN MESSAGE COMME LU
     * URL: PUT /api/chat/messages/{id}/read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message non trouvé'
            ], 404);
        }

        if ($message->receiver_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé'
            ], 403);
        }

        $message->is_read = true;
        $message->save();

        return response()->json([
            'success' => true,
            'message' => 'Message marqué comme lu'
        ]);
    }

    /**
     * 6. FERMER UNE CONVERSATION
     * URL: POST /api/chat/conversations/{id}/close
     */
    public function closeConversation(Request $request, $id)
    {
        $user = $request->user();

        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation non trouvée'
            ], 404);
        }

        // Seul l'admin peut fermer une conversation
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul l\'administrateur peut fermer une conversation'
            ], 403);
        }

        $conversation->status = 'closed';
        $conversation->closed_at = now();
        $conversation->save();

        return response()->json([
            'success' => true,
            'message' => 'Conversation fermée'
        ]);
    }

    /**
     * 7. SIGNALER UN MESSAGE (abus)
     * URL: POST /api/chat/messages/{id}/report
     */
    public function reportMessage(Request $request, $id)
    {
        $user = $request->user();

        $message = Message::find($id);

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Créer un signalement (table reports à créer)
        \App\Models\Report::create([
            'message_id' => $id,
            'reported_by' => $user->id,
            'reason' => $request->reason,
            'status' => 'pending'
        ]);

        // Notifier l'admin
        $this->notifyAdminAboutReport($message, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Message signalé. Un administrateur va examiner le signalement.'
        ]);
    }

    /**
     * 8. NOTIFICATION PUSH (envoyée à chaque message)
     */
    private function sendPushNotification($receiverId, $message, $conversation)
    {
        $receiver = User::find($receiverId);
        $sender = User::find($message->sender_id);

        if (!$receiver || !$sender) {
            return;
        }

        // Récupérer les tokens FCM du destinataire
        $tokens = \App\Models\DeviceToken::where('user_id', $receiverId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        // Préparer la notification
        $notificationData = [
            'title' => "Nouveau message de {$sender->name}",
            'body' => substr($message->content, 0, 100),
            'type' => 'chat',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'order_id' => $conversation->order_id
        ];

        // Envoyer via FCM (à implémenter)
        // $this->sendFirebaseNotification($tokens, $notificationData);

        // Pour l'instant, on log
        \Log::info("Notification push à envoyer à user {$receiverId}: ", $notificationData);
    }

    /**
     * 9. NOTIFIER ADMIN POUR SIGNALEMENT
     */
    private function notifyAdminAboutReport($message, $reason)
    {
        // Notifier l'admin via notification push
        \Log::info("Signalement message {$message->id}: {$reason}");
    }
}
