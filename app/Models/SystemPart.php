<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPart extends Model
{
    /** @use HasFactory<\Database\Factories\SystemPartFactory> */
    use HasFactory;

    protected $fillable = [
        'part_type',
        'manufacturer',
        'model_number',
        'list_price',
        'is_active',
    ];

    protected $casts = [
        'list_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function teamParts()
    {
        return $this->hasMany(TeamPart::class);
    }
}
