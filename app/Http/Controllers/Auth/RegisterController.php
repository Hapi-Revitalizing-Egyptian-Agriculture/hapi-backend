<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FarmerRegistrationRequest;
use App\Http\Requests\Auth\LandownerRegistrationRequest;

use App\Models\Farmer;
use App\Models\Landowner;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;



class RegisterController extends Controller
{
    protected function registerUser(array $user_data):User
    {
        $user_data['password'] = Hash::make($user_data['password']);
        return User::create($user_data);

    }
    protected function createFarmer(User $user,array $farmer_data):Farmer
    {
        return Farmer::create([
            'user_id' => $user->id,
            'land_id' => $farmer_data['land_id'],
        ]);

    }
    protected function createLandowner(User $user):Landowner
    {
        return landowner::create([
            'user_id' => $user->id,

        ]);

    }
    protected function generateToken(User $user):string
    {
       return  $user->createToken('user', ['app:all'])->plainTextToken;

    }

    //farmer-signUp
    public function registerFarmer(FarmerRegistrationRequest $request): JsonResponse
    {

        $validated_data=$request->validated();
        if(!$validated_data){
            return response()->json(data: ['error'=>$request->validator->errors()->messages()], status: 422);
        }
        $user = $this->registerUser($request->only(['username', 'phone_number', 'password','role']));
        $this->createFarmer($user,$request->only(['land_id']));

        $token=$this->generateToken($user);
        $success= [
        'token'=>$token,
            'username'=>$user->username,
            'land_id'=>$request->land_id

        ];
        return response()->json($success);

     }
    public function registerLandowner(LandownerRegistrationRequest $request): JsonResponse
    {

        $validated_data = $request->validated();

        if(!$validated_data){
            return response()->json(data: ['errors'=>$request->validator->errors()->messages()], status: 422);
        }

//        if (!$validated_data) {
//            $errors = [];
//            foreach ($request->validator->errors()->messages() as $field => $messages) {
//                // Access and display the first error only
//                    unset($errors['message']);
//                $errors[$field] = $messages[0];
//            }
//            //return one error for each field
//            return response()->json(['errors' => $errors], 422);
//        }

        $user = $this->registerUser($request->only(['username', 'phone_number', 'password','role']));
        $landowner = $this->createLandowner($user);
        event(new Registered($user));


        $token=$this->generateToken($user);
        $success= [
            'token'=>$token,
            'username'=>$user->username,
            'land_id'=>$landowner->land['unique_land_id']

        ];
        return response()->json($success);

    }
}