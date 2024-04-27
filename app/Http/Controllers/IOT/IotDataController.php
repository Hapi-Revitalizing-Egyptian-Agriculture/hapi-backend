<?php

namespace App\Http\Controllers\IOT;

use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\Iot;
use App\Models\Land;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IotDataController extends Controller
{
    //passing the land_id to the iot device

    protected $landId;


    public function __construct(string $landId = null)
    {
        $this->landId = $landId;
    }

    public function index($land_id)
    {
        $land = Land::where('unique_land_id', $land_id)->first();
        if (!$land) {
            return response()->json(['error' => 'Land not found'], 404);
        }
        $iotData = Iot::where('land_id', $land->id)->get();

        return response()->json(['iot_data' => $iotData], 200);
    }

//
    public function sendLand(): JsonResponse
    {
        if ($this->landId) {
            return response()->json(['land_id' => $this->landId], 200);
        } else {
            $land = Land::orderBy('id', 'desc')->first();
            if ($land) {
                return response()->json(['land_id' => $land->unique_land_id], 200);
            } else {
                return response()->json(['error' => 'No land available'], 404);
            }
        }
    }

    public function store(Request $request)
    {
        $validated_data = $request->validate([
            'land_id' => [
                'required',
                'string',
                Rule::exists('lands', 'unique_land_id'),
            ],
            'data' => 'required|json',
            'action_type' => 'required|string',
        ]);
        $land=Land::where('unique_land_id',$validated_data['land_id'])->first();
        if(!$land){
            return response()->json(['error' => 'Land not found'], 404);
        }
        $validated_data['land_id']=$land->id;
        $iot_data=new Iot();
        $iot_data->fill($validated_data);
        $iot_data->action_time = now();
        $iot_data->save();
        return response()->json(['message' => 'Data saved successfully'], 201);
    }

    public function update(Request $request,$id){
        $iot_data = Iot::where('id', $id)->first();
        if (!$iot_data) {
            return response()->json(['error' => 'IoT data not found for the specified land_id'], 404);
        }
        $validated_data = $request->validate([
            'data' => 'sometimes|required|json',
        ]);
        $iot_data->update($validated_data);

        return response()->json(['message' => 'Data updated successfully'], 200);
    }

//    public function destroy(Request $request,$id){
//        $iot_data = Iot::where('id', $id)->first();
//        if (!$iot_data) {
//            return response()->json(['error' => 'IoT data not found '], 404);
//        }
//        $iot_data->delete();
//
//        return response()->json(['message' => 'Data deleted successfully'], 200);
//
//    }

}
