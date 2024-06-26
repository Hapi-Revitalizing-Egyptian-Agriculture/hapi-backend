<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CropLandHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /** @noinspection PhpUndefinedFieldInspection */

    public function check_password(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }
        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json(['error' => 'Incorrect password'], 401);
        }

        return response()->json(null);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        CropLandHistory::where('land_id', $request->user()->landowner->lands->first()->id)->delete();
        $request->user()->delete();

        return response()->json(null);
    }
    //there is no change password yet
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();
        $user->update(['password' => bcrypt($request->new_password)]);
        return response()->json(['message' => 'Password changed successfully']);
    }
    //list of farmer for specific land
    public function listFarmers(Request $request): JsonResponse
    {
        $landowner = Auth::user()->landowner;
        $land = $landowner->lands()->first();
        $farmers = $land->farmers;
        //list farmers name
        $farmers_names = $farmers->map(function ($farmer) {
            return $farmer->user->username;
        });

        return response()->json($farmers_names);
    }


}
