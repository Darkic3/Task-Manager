<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'completed_at',
        'estimated_hours',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'to_do':
                return 'primary';
            case 'in_progress':
                return 'warning';
            case 'on_hold':
                return 'secondary';
            case 'in_review':
                return 'info';
            case 'completed':
                return 'success';
            default:
                return 'secondary';
        }
    }

    public function checklistItems()
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
