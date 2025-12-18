<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPart extends Model
{
    /** @use HasFactory<\Database\Factories\TeamPartFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'system_part_id',
        'multiplier',
        'static_price',
        'team_price',
    ];

    protected $casts = [
        'multiplier' => 'decimal:3',
        'static_price' => 'decimal:2',
        'team_price' => 'decimal:2',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function systemPart()
    {
        return $this->belongsTo(SystemPart::class);
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($teamPart) {
            // Calculate team_price based on multiplier or static_price
            $systemPart = $teamPart->systemPart;

            if ($teamPart->static_price !== null) {
                $teamPart->team_price = $teamPart->static_price;
            } elseif ($teamPart->multiplier !== null && $systemPart) {
                $teamPart->team_price = $systemPart->list_price * $teamPart->multiplier;
            }
        });
    }
}
