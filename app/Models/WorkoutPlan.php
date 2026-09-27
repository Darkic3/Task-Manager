<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkoutPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const WEEKDAYS = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    protected $fillable = [
        'user_id', 'parent_id', 'title', 'week_number', 'cycle_no', 'goal',
        'description', 'start_date', 'status',
    ];

    protected $casts = ['start_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkoutDay::class)->orderBy('sort_order');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(WorkoutRule::class)->orderBy('sort_order');
    }
}
