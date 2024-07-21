<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'products_id',
        'url',
    ];

    public function getUrlAttribute($url)
    {
        return config('app.url') . Storage::url($url);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'products_id','id');
    }
}
