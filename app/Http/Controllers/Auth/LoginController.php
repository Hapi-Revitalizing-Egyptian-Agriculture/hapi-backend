<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /** @noinspection PhpUndefinedFieldInspection */
    public function login(LoginRequest $request): JsonResponse
    {
// Validate the login request
        $validatedData = $request->validated();
        if (!$validatedData) {
            return response()->json(['error' => $request->validator->errors()->messages()], 422);
        }

// Attempt to authenticate the user
        $credentials = $request->only('phone_number', 'password');
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('authToken')->plainTextToken;
            $responseData = [
                'token' => $token,
                'username'=>$user->username,
                'role' => $user->role,

            ];


            if ($user->role === 'landowner' && $user->landowner) {
                $responseData['unique_land_id'] = $user->landowner->land->unique_land_id;
            }

            return response()->json($responseData);
        }

        // Invalid credentials
        return response()->json(['message' => 'Invalid Number or password'], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return \response()->json(['message'=>'Logged out successfully']);
    }
}