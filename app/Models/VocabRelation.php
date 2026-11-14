<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VocabRelation extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'related_card_id',
        'relation_type',
    ];

    public function card()
    {
        return $this->belongsTo(VocabCard::class, 'card_id');
    }

    public function relatedCard()
    {
        return $this->belongsTo(VocabCard::class, 'related_card_id');
    }
}
