<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhoneCameroon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // ==========================================
    // 1. INSCRIPTION (email + mot de passe)
    // ==========================================
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => ['required', 'string', 'max:20', new PhoneCameroon],
            'password' => 'required|string|min:6',
            'role' => 'required|in:client,commercant,grossiste,livreur'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'role' => $user->role
        ], 201);
    }

    // ==========================================
    // 2. CONNEXION (email + mot de passe)
    // ==========================================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $user = User::where('email', $request->email)->first();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'role' => $user->role
        ]);
    }

    // ==========================================
    // 3. CONNEXION VIA GOOGLE (redirection)
    // ==========================================
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // ==========================================
    // 4. CALLBACK GOOGLE
    // ==========================================
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            $user = User::where('email', $googleUser->getEmail())->first();
            
            if (!$user) {
                // Créer un nouvel utilisateur
                $user = User::create([
                    'name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => Hash::make(uniqid()),
                    'role' => 'client', // Par défaut client
                    'email_verified_at' => now()
                ]);
            }
            
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'role' => $user->role
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'authentification Google'
            ], 500);
        }
    }

    // ==========================================
    // 5. CONNEXION VIA FACEBOOK (redirection)
    // ==========================================
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    // ==========================================
    // 6. CALLBACK FACEBOOK
    // ==========================================
    public function handleFacebookCallback()
    {
        try {
            $facebookUser = Socialite::driver('facebook')->stateless()->user();
            
            $user = User::where('email', $facebookUser->getEmail())->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $facebookUser->getName(),
                    'email' => $facebookUser->getEmail(),
                    'avatar' => $facebookUser->getAvatar(),
                    'password' => Hash::make(uniqid()),
                    'role' => 'client',
                    'email_verified_at' => now()
                ]);
            }
            
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token,
                'role' => $user->role
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'authentification Facebook'
            ], 500);
        }
    }

    // ==========================================
    // 7. DÉCONNEXION
    // ==========================================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Déconnecté avec succès'
        ]);
    }

    // ==========================================
    // 8. PROFIL UTILISATEUR
    // ==========================================
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    // ==========================================
    // 9. MODIFIER PROFIL
    // ==========================================
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|string|nullable'
        ]);
        
        $user->update($request->only(['name', 'phone', 'avatar']));
        
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }
}