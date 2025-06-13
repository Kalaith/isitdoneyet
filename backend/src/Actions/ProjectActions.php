<?php

namespace App\Actions;

use App\Models\Project;
use App\Utils\Logger;
use Exception;

class ProjectActions
{
    private Logger $logger;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }    /**
     * Get all root projects with their complete hierarchies
     */
    public function getAllProjects(): array
    {
        try {
            $this->logger->info('DEBUG: Starting getAllProjects');
            
            $projects = Project::roots()
                ->with(['children' => function ($query) {
                    $query->with('children.children.children.children'); // Load 5 levels deep
                }])
                ->orderBy('created_at', 'desc')
                ->get();
                
            $this->logger->info('DEBUG: Raw projects loaded', [
                'count' => $projects->count(),
                'first_project_children_count' => $projects->first() ? $projects->first()->children->count() : 0
            ]);
            
            // Debug: Log raw project data
            foreach ($projects as $project) {
                $this->logger->info('DEBUG: Project loaded', [
                    'id' => $project->id,
                    'title' => $project->title,
                    'children_loaded' => $project->relationLoaded('children'),
                    'children_count' => $project->children ? $project->children->count() : 0,
                    'children_ids' => $project->children ? $project->children->pluck('id')->toArray() : []
                ]);
            }
                  $result = $projects->map(function ($project) {
                return $this->formatProjectWithProgress($project);
            })->toArray();
            
            $this->logger->info('DEBUG: Formatted projects result', [
                'count' => count($result),
                'first_project_children' => isset($result[0]['children']) ? count($result[0]['children']) : 0,
                'full_first_project' => isset($result[0]) ? json_encode($result[0]) : 'none'
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching all projects', ['error' => $e->getMessage()]);
            throw $e;
        }
    }/**
     * Get a specific project by ID with its hierarchy
     */
    public function getProjectById(int $id): ?array
    {
        try {
            $project = Project::with(['children' => function ($query) {
                $query->with('children.children.children.children'); // Load 5 levels deep
            }, 'parent'])->find($id);
            
            if (!$project) {
                return null;
            }
            
            return $this->formatProjectWithProgress($project);
            
        } catch (Exception $e) {
            $this->logger->error('Error fetching project by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create a new project
     */
    public function createProject(array $data): array
    {
        try {
            $project = Project::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'completed' => false,
                'parent_id' => $data['parent_id'] ?? null
            ]);
            
            $this->logger->info('Project created', ['project_id' => $project->id]);
            
            return $this->formatProjectWithProgress($project);
            
        } catch (Exception $e) {
            $this->logger->error('Error creating project', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update a project
     */
    public function updateProject(int $id, array $data): ?array
    {
        try {
            $project = Project::find($id);
            
            if (!$project) {
                return null;
            }
            
            $project->update([
                'title' => $data['title'] ?? $project->title,
                'description' => $data['description'] ?? $project->description,
                'completed' => $data['completed'] ?? $project->completed
            ]);
            
            $this->logger->info('Project updated', ['project_id' => $project->id]);
            
            return $this->formatProjectWithProgress($project);
            
        } catch (Exception $e) {
            $this->logger->error('Error updating project', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Mark a project as complete and handle propagation
     */
    public function markProjectComplete(int $id): array
    {
        try {
            $project = Project::with('parent')->find($id);
            
            if (!$project) {
                throw new Exception('Project not found');
            }
            
            $completedProjects = $project->markCompleteAndPropagate();
            
            $this->logger->info('Project marked complete', [
                'project_id' => $id,
                'completed_count' => count($completedProjects)
            ]);
            
            return [
                'completed_projects' => array_map(function ($proj) {
                    return $this->formatProjectWithProgress($proj);
                }, $completedProjects)
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Error marking project complete', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }    /**
     * Delete a project and all its children
     */
    public function deleteProject(int $id): bool
    {
        try {
            $project = Project::with('children')->find($id);
            
            if (!$project) {
                return false;
            }
            
            // Delete children recursively
            $this->deleteChildren($project);
            
            // Delete the project itself
            $project->delete();
            
            $this->logger->info('Project deleted', ['project_id' => $id]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Error deleting project', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Add a subtask to a project
     */
    public function addSubtask(int $parentId, array $data): ?array
    {
        try {
            $parent = Project::find($parentId);
            
            if (!$parent) {
                return null;
            }
            
            $subtask = Project::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? '',
                'completed' => false,
                'parent_id' => $parentId
            ]);
            
            $this->logger->info('Subtask added', [
                'parent_id' => $parentId,
                'subtask_id' => $subtask->id
            ]);
            
            return $this->formatProjectWithProgress($subtask);
            
        } catch (Exception $e) {
            $this->logger->error('Error adding subtask', [
                'parent_id' => $parentId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }    /**
     * Format project data with progress calculation
     */
    private function formatProjectWithProgress(Project $project): array
    {
        $this->logger->info('DEBUG: formatProjectWithProgress called', [
            'project_id' => $project->id,
            'project_title' => $project->title,
            'children_loaded' => $project->relationLoaded('children'),
            'raw_children_count' => $project->children ? $project->children->count() : 'null'
        ]);
        
        // Ensure children relationship is loaded
        if (!$project->relationLoaded('children')) {
            $this->logger->info('DEBUG: Loading children relationship for project', ['id' => $project->id]);
            $project->load('children');
        }
        
        $this->logger->info('DEBUG: After loading children', [
            'project_id' => $project->id,
            'children_count' => $project->children ? $project->children->count() : 'null',
            'children_ids' => $project->children ? $project->children->pluck('id')->toArray() : []
        ]);
        
        $data = [
            'id' => $project->id,
            'title' => $project->title,
            'description' => $project->description,
            'completed' => $project->completed,
            'parent_id' => $project->parent_id,
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
            'progress' => $project->calculateProgress(),
            'children' => []
        ];
        
        // Format children recursively
        if ($project->children && $project->children->count() > 0) {
            $this->logger->info('DEBUG: Formatting children', [
                'project_id' => $project->id,
                'children_count' => $project->children->count()
            ]);
            
            $data['children'] = $project->children->map(function ($child) {
                return $this->formatProjectWithProgress($child);
            })->toArray();
            
            $this->logger->info('DEBUG: Children formatted', [
                'project_id' => $project->id,
                'formatted_children_count' => count($data['children'])
            ]);        } else {
            $this->logger->info('DEBUG: No children to format', [
                'project_id' => $project->id,
                'children_is_null' => is_null($project->children),
                'children_count' => $project->children ? $project->children->count() : 'null'
            ]);
        }
        
        $this->logger->info('DEBUG: Final formatted data for project', [
            'project_id' => $project->id,
            'children_count_in_result' => count($data['children']),
            'formatted_data' => json_encode($data)
        ]);
        
        return $data;
    }
    
    /**
     * Recursively delete children
     */
    private function deleteChildren(Project $project): void
    {
        foreach ($project->children as $child) {
            $this->deleteChildren($child);
            $child->delete();
        }
    }
}
