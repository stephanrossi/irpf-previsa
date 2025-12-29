<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationIsento extends Model
{
    use HasFactory;

    protected $fillable = [
        'declaration_id',
        'cod_isento',
        'valor',
    ];

    public function declaration()
    {
        return $this->belongsTo(Declaration::class);
    }
}
