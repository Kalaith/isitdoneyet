<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'projects';
    
    protected $fillable = [
        'title',
        'description',
        'completed',
        'parent_id',
        'created_at',
        'updated_at'
    ];
    
    protected $casts = [
        'completed' => 'boolean',
        'parent_id' => 'integer'
    ];
    
    // Self-referencing relationship for parent/child tasks
    public function parent()
    {
        return $this->belongsTo(Project::class, 'parent_id');
    }
    
    public function children()
    {
        return $this->hasMany(Project::class, 'parent_id')->orderBy('created_at');
    }
    
    // Recursive children relationship
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }
    
    // Get root projects (those with no parent)
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }    // Calculate progress based on completed children
    public function calculateProgress(): int
    {
        // Check which relationship is loaded and use it
        $children = null;
        if ($this->relationLoaded('allChildren')) {
            $children = $this->allChildren;
        } elseif ($this->relationLoaded('children')) {
            $children = $this->children;
        } else {
            // Load children if no relationship is loaded
            $this->load('children');
            $children = $this->children;
        }
        
        // Debug logging
        error_log("DEBUG Project #{$this->id} calculateProgress: children count = " . $children->count());
        
        if ($children->isEmpty()) {
            return $this->completed ? 100 : 0;
        }
        
        $totalTasks = $this->countAllDescendants();
        $completedTasks = $this->countCompletedDescendants();
        
        error_log("DEBUG Project #{$this->id}: total={$totalTasks}, completed={$completedTasks}");
        
        return $totalTasks > 0 ? (int) round(($completedTasks / $totalTasks) * 100) : 0;
    }private function countAllDescendants(): int
    {
        // Use whichever relationship is loaded
        $children = null;
        if ($this->relationLoaded('allChildren')) {
            $children = $this->allChildren;
        } elseif ($this->relationLoaded('children')) {
            $children = $this->children;
        } else {
            $this->load('children');
            $children = $this->children;
        }
        
        $count = 0;
        foreach ($children as $child) {
            $count += 1;
            $count += $child->countAllDescendants();
        }
        return $count;
    }

    private function countCompletedDescendants(): int
    {
        // Use whichever relationship is loaded
        $children = null;
        if ($this->relationLoaded('allChildren')) {
            $children = $this->allChildren;
        } elseif ($this->relationLoaded('children')) {
            $children = $this->children;
        } else {
            $this->load('children');
            $children = $this->children;
        }
        
        $count = 0;
        foreach ($children as $child) {
            if ($child->completed) {
                $count += 1;
            }
            $count += $child->countCompletedDescendants();
        }
        return $count;
    }
      // Mark as complete and check if parent should be completed
    public function markCompleteAndPropagate(): array
    {
        $completedProjects = [];
        
        if (!$this->completed) {
            $this->completed = true;
            $this->save();
            $completedProjects[] = $this;
        }
        
        // Note: We don't automatically mark parents as complete
        // The user must explicitly mark each level as complete by answering "Is it done yet?"
        // This preserves the core principle of the app
        
        return $completedProjects;
    }
}
