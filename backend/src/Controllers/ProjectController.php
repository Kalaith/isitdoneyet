<?php

namespace App\Controllers;

use App\Actions\ProjectActions;
use App\Utils\Logger;
use Exception;

class ProjectController
{
    private ProjectActions $projectActions;
    private Logger $logger;
    
    public function __construct(ProjectActions $projectActions, Logger $logger)
    {
        $this->projectActions = $projectActions;
        $this->logger = $logger;
    }    
    /**
     * Get all projects
     */
    public function getAllProjects(): array
    {
        try {
            $projects = $this->projectActions->getAllProjects();
            return [
                'success' => true,
                'data' => $projects
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to fetch projects: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch projects: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get project by ID
     */
    public function getProjectById(int $id): array
    {
        try {
            $project = $this->projectActions->getProjectById($id);
            
            if (!$project) {
                return [
                    'success' => false,
                    'message' => 'Project not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $project
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to fetch project: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch project: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new project
     */
    public function createProject(array $data): array
    {
        try {
            if (!isset($data['title']) || empty(trim($data['title']))) {
                return [
                    'success' => false,
                    'message' => 'Title is required'
                ];
            }
            
            $project = $this->projectActions->createProject($data);
            
            return [
                'success' => true,
                'data' => $project
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create project: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create project: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a project
     */
    public function updateProject(int $id, array $data): array
    {
        try {
            $project = $this->projectActions->updateProject($id, $data);
            
            if (!$project) {
                return [
                    'success' => false,
                    'message' => 'Project not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $project
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update project: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update project: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark project as complete
     */
    public function markProjectComplete(int $id): array
    {
        try {
            $result = $this->projectActions->markProjectComplete($id);
            
            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to mark project complete: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to mark project complete: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a project
     */
    public function deleteProject(int $id): array
    {
        try {
            $deleted = $this->projectActions->deleteProject($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Project not found'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Project deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete project: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete project: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Add subtask to a project
     */
    public function addSubtask(int $parentId, array $data): array
    {
        try {
            if (!isset($data['title']) || empty(trim($data['title']))) {
                return [
                    'success' => false,
                    'message' => 'Title is required'
                ];
            }
            
            $subtask = $this->projectActions->addSubtask($parentId, $data);
            
            if (!$subtask) {
                return [
                    'success' => false,
                    'message' => 'Parent project not found'
                ];
            }
            
            return [
                'success' => true,            'data' => $subtask
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Failed to add subtask: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to add subtask: ' . $e->getMessage()
            ];
        }
    }
}
