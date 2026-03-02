<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeSession extends Model
{
    protected $fillable = [
        'url',
        'total_found',
        'total_processed',
        'total_failed',
        'status',
        'log',
    ];

    public function appendLog(string $message): void
    {
        $timestamp = now()->format('H:i:s');
        $this->log = ($this->log ?? '') . "[{$timestamp}] {$message}\n";
        $this->save();
    }
}
