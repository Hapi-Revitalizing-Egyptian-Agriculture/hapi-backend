<?php /** @noinspection PhpUndefinedFieldInspection */

namespace App\Http\Controllers\Crop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NotificationController;
use App\Http\Requests\ImageRequest;
use App\Models\Crop;
use App\Models\Detection;
use App\Models\Disease;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class DetectionController extends Controller
{
    protected NotificationController $notificationController;
    public function __construct(NotificationController $notificationController)
    {
        $this->notificationController = $notificationController;
    }

    public function detect(ImageRequest $request): JsonResponse
    {
        try {
            $this->validateImage($request);
            $validatedData = $request->validated();

            // Validate and retrieve crop data
            $validatedCrop = $validatedData['crop'];
            $crop = Crop::firstOrCreate(['name' => $validatedCrop]);
            $crop_id = $crop->id;

            $image = $request->file('image');


            $client = new Client();

            $response = $client->request('POST', 'http://127.0.0.1:1000/', [
                'query' => [
                    'crop_name' => $validatedCrop
                ],
                'multipart' => [
                    [
                        'name' => 'image',
                        'contents' => fopen($image->getPathname(), 'r'),
                        'filename' => $image->getClientOriginalName()
                    ]
                ],
                'timeout' => 60
            ]);

            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getBody(), true);
                $transformedResponse = $this->transformResponseForDetect($responseData, $crop_id);
                if ($transformedResponse['certainty'] < 50) {
                    return response()->json(['error' => 'The AI model is not confident about the detection.'], 400);
                }
                $this->processDetectionResult($responseData, $image, $crop_id);

                return response()->json($transformedResponse);
            }
            return response()->json(['error' => "API request failed. Status code: " . $response->getStatusCode()], 500);
        } catch (RequestException $e) {
            return response()->json(['error' => 'Failed to connect to AI service.'], 500);
        }
    }

    private function validateImage(ImageRequest $request)
    {
        $validatedData = $request->validated();

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'Image is required.'], 400);
        }
        return null;
    }

    public function processDetectionResult(array $result, UploadedFile $image, int $cropId): void
    {
        $user = Auth::guard('sanctum')->user();
        //dd($user);
        if ($user) {
            $landId = $this->retrieveUserLandId();
            $this->store($user->id, $result, $image, $cropId);
            $this->notificationController->createNewDetectionNotification($landId, $user->username);
        }
    }

    public function retrieveUserLandId(): ?int
    {
        $user = Auth::guard('sanctum')->user();
        //        ($user);
        if (!$user) {
            return null;
        } else {
            if ($user->role === 'landowner') {
                return $user->landowner->lands()->first()->id;
            } else {
                return $user->farmer->land_id;
            }
        }
    }

    public function store($user_id, $result, $image, $crop_id)
    {

        $path = $image->storeAs('/detections', $image->getClientOriginalName(), 'public');
        $detection = new Detection();
        $detection->user_id = $user_id;
        $detection->land_id = $this->retrieveUserLandId();
        $detection->image = $path;
        $detection->detection = json_encode($result);
        $detection->crop_id = $crop_id;
        $now = Carbon::now('Africa/Cairo')->addHour();
        $detection->detected_at = $now;
        $detection->save();
    }

    private function enhanceDetections($detections, $details = false)
    {
        return $detections->map(function ($detection) use ($details) {
            $detectionData = json_decode($detection->detection, true);
            $imgName = basename($detection->image);
            $imageUrl = Storage::url("detections/{$imgName}");
            $timestamp = strtotime($detection->detected_at);
            $date = date('d/m/Y', $timestamp);
            $time = date('H:i A', $timestamp);

            $result = [
                'id' => $detection->id,
                'username' => $detection->user->username,
                'image_url' => $imageUrl,
                'date' => $date,
                'time' => $time,

            ];
            if ($details) {
                $diseaseName = $detectionData['plant_health'][1] ?? 'Unknown';
                $diseaseName = str_replace("_", ' ', $diseaseName);
                $diseaseName = strtolower($diseaseName) === 'healthy' ? '' : $diseaseName;

                $certainty = $detectionData['confidence'] ?? 0;
                $certainty = str_replace('%', '', $certainty);
                $infoLink = $this->generateInfoLink($diseaseName, $detection->crop->id);


                $result['disease_name'] = $diseaseName;
                $result['certainty'] = (float)$certainty;
                $result['info_link'] = $infoLink;
                $result['crop'] = $detection->crop->name;
                unset($result['id']);
            }
            return $result;
        });
    }

    public function history(Request $request): JsonResponse
    {
        $id = $request->query('id');
        if (!$id) {
            return response()->json(['error' => 'ID parameter is missing'], 400);
        }

        $comparisonOperator = $id === "1" ? '>=' : '>';
        $detectionHistory = Detection::where('land_id', $this->retrieveUserLandId())
            ->where('id', $comparisonOperator, $id)
            ->orderBy('detected_at', 'desc')
            ->get();

        if ($detectionHistory->isEmpty()) {
            return response()->json([]);
        }

        return response()->json($this->enhanceDetections($detectionHistory));

    }

    public function show($id)
    {
        $detection = Detection::find($id);
        if (!$detection) {
            return response()->json(['error' => 'Detection not found'], 404);
        }

        $user = Auth::guard('sanctum')->user();
        if (!$user || ($user->id !== $detection->user_id && $detection->land_id !== $this->retrieveUserLandId())) {
            return response()->json(['error' => 'Unauthorized to view this detection'], 403);
        }

        $enhancedDetection = $this->enhanceDetections(collect([$detection]), true)->first();

        return response()->json($enhancedDetection);
    }


    // modifying the response to match with mobile ui

    private function transformResponseForDetect($responseData, $crop_id)
    {
        $plantHealth = $responseData['plant_health'][1] ?? 'Unknown';
        $certainty = $responseData['confidence'] ?? '0%';
        //remove %
        $certainty = str_replace('%', '', $certainty);
        $plantHealth = str_replace("_", ' ', $plantHealth);

        $diseaseName = strtolower($plantHealth) === 'healthy' ? '' : $plantHealth;

        return [
            'disease_name' => $diseaseName,
            'certainty' => (float)$certainty,
            'info_link' => $this->generateInfoLink($plantHealth, $crop_id),
        ];
    }

    private function generateInfoLink($plantHealth, $crop_id): string
    {
        if ($plantHealth === 'healthy') {
            return "https://mariamabdulhaleem3.github.io/HAPI-Website/#/Library/{$crop_id}";
        } else {
            $disease_id = Disease::where('crop_id', $crop_id)->where('name', $plantHealth)->first()->id;
            return "https://mariamabdulhaleem3.github.io/HAPI-Website/#/Library/{$crop_id}/diseases/{$disease_id}";
        }


    }


}
