<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crop extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'nitrogen', 'phosphorus', 'potassium'

    ];

    public static function findOrCreate(array $attributes)
    {
        $crop = self::where($attributes)->first();

        if (!$crop) {
            $crop = self::create($attributes);
        }

        return $crop;
    }
    public function detection()
    {
        return $this->hasMany(Detection::class);
    }
    public function land()
    {
        return $this->belongsTo(Land::class);
    }

    public function landHistory()
    {
        return $this->hasMany(CropLandHistory::class);
    }

    public function detections()
    {
        return $this->hasMany(Detection::class);
    }

    public function diseases()
    {
        return $this->hasMany(Disease::class);
    }
}
