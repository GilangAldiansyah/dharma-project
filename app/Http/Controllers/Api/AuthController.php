<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User Authentication & Authorization"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="User login",
     *     tags={"Authentication"},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *               required={"email","password"},
     *               @OA\Property(property="email", type="string", example="user@example.com"),
     *               @OA\Property(property="password", type="string", example="password123")
     *          )
     *     ),
     *
     *     @OA\Response(
     *          response=200,
     *          description="Login successful"
     *     ),
     *     @OA\Response(
     *          response=401,
     *          description="Invalid credentials"
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        // Optional: revoke old tokens
        // $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="User registration",
     *     tags={"Authentication"},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *               required={"name","email","password","password_confirmation"},
     *               @OA\Property(property="name", type="string", example="John Doe"),
     *               @OA\Property(property="email", type="string", example="john@example.com"),
     *               @OA\Property(property="password", type="string", example="password123"),
     *               @OA\Property(property="password_confirmation", type="string", example="password123")
     *          )
     *     ),
     *
     *     @OA\Response(
     *          response=201,
     *          description="Registration successful"
     *     ),
     *     @OA\Response(
     *          response=422,
     *          description="Validation error"
     *     )
     * )
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Logout user (requires token)",
     *     tags={"Authentication"},
     *     security={{"BearerAuth":{}}},
     *
     *     @OA\Response(
     *          response=200,
     *          description="Logged out successfully"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/auth/user",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"BearerAuth":{}}},
     *
     *     @OA\Response(
     *          response=200,
     *          description="User profile"
     *     )
     * )
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ], 200);
    }
}
