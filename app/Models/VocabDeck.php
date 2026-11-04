<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabDeck extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cards()
    {
        return $this->hasMany(VocabCard::class, 'deck_id');
    }

    public function dueCards()
    {
        return $this->hasMany(VocabCard::class, 'deck_id')->due();
    }

    public function getDueCountAttribute(): int
    {
        return $this->dueCards()->count();
    }
}
