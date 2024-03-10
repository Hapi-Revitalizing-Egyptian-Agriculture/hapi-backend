<?php /** @noinspection ALL */

namespace App\Http\Controllers\Crop;

use App\Events\LandInformationReceived;
use App\Http\Controllers\Controller;
use App\Models\Crop;
use App\Models\CropLandHistory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;



class RecommendationController extends Controller
{
    private $landInfo;
    public function recommend(Request $request){

        if(! Auth::user()->role == 'landowner'){
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $land = Auth::user()->landowner->land;

        try {
            $response = Http::post('https://e376e3b7-2a57-4420-9342-3717ad9cec0a.mock.pstmn.io/land-info', [
                'land_id' => $land->unique_land_id,
            ]);

            if ($response->successful()) {
                $landInfo = $response->json();
                $cropRecommendations = $this->getCropRecommendation($landInfo);
                $this->sendCropRecommendations($cropRecommendations);
            } else {
                //just for test ,I'll refactor it later..
                switch ($response->status()) {
                    case 400:
                        return response()->json(['error' => 'Bad request'], 400);
                    case 404:
                        return response()->json(['error' => 'Land information not found'], 404);
                    case 500:
                        return response()->json(['error' => 'Server error'], 500);
                    default:
                        return response()->json(['error' => 'Failed to get land information'], $response->status());
                }
            }
        } catch (\Exception $e) {
            Log::error('Error processing HTTP request: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to process HTTP request'], 500);
        }

    }
    private function getCropRecommendation($landInfo)
    {
//get the crop recommendation
        $response = Http::post("https://e376e3b7-2a57-4420-9342-3717ad9cec0a.mock.pstmn.io/recommend-crop", $landInfo); //url ai
        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to get crop recommendation.'], $response->status());
        }
        return $response->json();
    }

    private function sendCropRecommendations($cropRecommendations)
    {
// Send the response with crop recommendations and success message
        return response()->json([
            'message' => 'Land information received successfully',
            'crop_recommendations' => $cropRecommendations,
        ], 200);
    }

}
