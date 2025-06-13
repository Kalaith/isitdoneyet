// API Service for Is It Done Yet?
class ApiService {
    constructor() {
        this.baseURL = 'https://webhatchery.au/isitdoneyet/api';
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers,
            },
            ...options,
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    }

    // Project endpoints
    async getAllProjects() {
        return this.request('/projects');
    }

    async getProject(id) {
        return this.request(`/projects/${id}`);
    }

    async createProject(projectData) {
        return this.request('/projects', {
            method: 'POST',
            body: JSON.stringify(projectData),
        });
    }

    async updateProject(id, projectData) {
        return this.request(`/projects/${id}`, {
            method: 'PUT',
            body: JSON.stringify(projectData),
        });
    }

    async deleteProject(id) {
        return this.request(`/projects/${id}`, {
            method: 'DELETE',
        });
    }

    async markProjectComplete(id) {
        return this.request(`/projects/${id}/complete`, {
            method: 'POST',
        });
    }

    async addSubtask(parentId, subtaskData) {
        return this.request(`/projects/${parentId}/subtasks`, {
            method: 'POST',
            body: JSON.stringify(subtaskData),
        });
    }

    // Health check
    async getStatus() {
        return this.request('/status');
    }
}

// Export the API service instance
const apiService = new ApiService();
