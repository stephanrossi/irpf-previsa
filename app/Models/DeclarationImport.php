<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'declaration_id',
        'is_retificadora',
        'recibo_anterior',
        'source_file_path',
        'source_sha256',
        'imported_at',
    ];

    protected $casts = [
        'is_retificadora' => 'boolean',
        'imported_at' => 'datetime',
    ];

    public function declaration()
    {
        return $this->belongsTo(Declaration::class);
    }
}
