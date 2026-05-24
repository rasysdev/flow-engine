<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionRun extends Model
{
    protected $table = 'collection_runs';

    protected $fillable = [
        'client_id',
        'client_name',
        'account_id',
        'status',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'checks' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function results()
    {
        return $this->hasMany(CollectionResult::class);
    }
}
