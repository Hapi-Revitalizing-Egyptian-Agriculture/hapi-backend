<?php

namespace App\Http\Controllers\IOT;

use App\Http\Controllers\Controller;
use App\Models\Iot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IotDataController extends Controller
{
    public function index($land_id){
        $iotData = Iot::where('land_id', $land_id)->get();

        return response()->json(['iot_data' => $iotData], 200);
    }
    public function store(Request $request){
        $validated_data = $request->validate([
            'land_id' => [
                'required',
                'string',
                Rule::exists('lands', 'unique_land_id'),
            ],
            'data' => 'required|json',
        ]);
        $iot_data=new Iot();
        $iot_data->fill($validated_data);
        $iot_data->save();
        return response()->json(['message' => 'Data saved successfully'], 201);
    }

    public function update(Request $request,$land_id){
        $iot_data = Iot::where('land_id', $land_id)->first();
        if (!$iot_data) {
            return response()->json(['error' => 'IoT data not found for the specified land_id'], 404);
        }
        $validated_data = $request->validate([
            'data' => 'sometimes|required|json',
        ]);
        $iot_data->update($validated_data);

        return response()->json(['message' => 'Data updated successfully'], 200);
    }

    public function destroy(Request $request,$land_id){
        $iot_data = Iot::where('land_id', $land_id)->first();
        if (!$iot_data) {
            return response()->json(['error' => 'IoT data not found for the specified land_id'], 404);
        }
        $iot_data->delete();

        return response()->json(['message' => 'Data deleted successfully'], 200);

    }

}