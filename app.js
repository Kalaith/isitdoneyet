// Application state
let projects = [];
let currentTaskId = null;
let nextId = 1;

// Sample data
const sampleProjects = [
    {
        "id": 1,
        "title": "Launch Personal Website",
        "description": "Create and deploy a personal portfolio website",
        "completed": false,
        "parent_id": null,
        "children": [
            {
                "id": 2,
                "title": "Design the website layout",
                "description": "",
                "completed": true,
                "parent_id": 1,
                "children": []
            },
            {
                "id": 3,
                "title": "Write content for all pages",
                "description": "",
                "completed": false,
                "parent_id": 1,
                "children": [
                    {
                        "id": 4,
                        "title": "Write About page",
                        "description": "",
                        "completed": true,
                        "parent_id": 3,
                        "children": []
                    },
                    {
                        "id": 5,
                        "title": "Write Portfolio page",
                        "description": "",
                        "completed": false,
                        "parent_id": 3,
                        "children": []
                    }
                ]
            },
            {
                "id": 6,
                "title": "Set up hosting and deploy",
                "description": "",
                "completed": false,
                "parent_id": 1,
                "children": []
            }
        ]
    }
];

// Test API connection
async function testApiConnection() {
    try {
        const response = await apiService.getStatus();
        console.log('✅ API Connection Test Successful:', response);
        
        // Show success message in UI
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--color-success, #28a745);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1001;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = '✅ API Connected Successfully!';
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 2000);
        
        return true;
    } catch (error) {
        console.error('❌ API Connection Failed:', error);
        
        // Show error message in UI
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background: #dc3545;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1001;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = '❌ API Connection Failed - Using offline mode';
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
        
        return false;
    }
}

// Initialize application
document.addEventListener('DOMContentLoaded', async function() {
    console.log('🚀 Initializing Is It Done Yet? app...');
    
    try {
        // Test API connection first
        const apiConnected = await testApiConnection();
        
        if (apiConnected) {
            // Load projects from API
            await loadProjectsFromAPI();
            console.log('✅ Loaded projects from API');
        } else {
            // Fallback to sample data if API is not available
            console.warn('⚠️ API not available, using sample data');
            projects = [...sampleProjects];
            nextId = getMaxId(projects) + 1;
        }
    } catch (error) {
        console.warn('⚠️ Failed to load from API, using sample data:', error);
        // Fallback to sample data if API is not available
        projects = [...sampleProjects];
        nextId = getMaxId(projects) + 1;
    }
    
    initializeEventListeners();
    renderProjects();
    updateEmptyState();
    
    console.log('✅ App initialized successfully');
});

// Load projects from API
async function loadProjectsFromAPI() {
    try {
        const response = await apiService.getAllProjects();
        console.log('✅ Raw API response:', response);
        
        if (response.success && response.data) {
            // API already returns hierarchical data, no need to build hierarchy
            projects = response.data;
            nextId = getMaxId(projects) + 1;
            console.log('✅ Loaded projects from API:', projects);
        } else {
            throw new Error('Invalid API response format');
        }
    } catch (error) {
        console.error('❌ Failed to load projects from API:', error);
        throw error;
    }
}

// Convert flat API data to hierarchical structure
function buildHierarchy(flatData) {
    const projects = [];
    const itemMap = {};
    
    // First pass: create all items
    flatData.forEach(item => {
        itemMap[item.id] = {
            ...item,
            children: []
        };
    });
    
    // Second pass: build hierarchy
    flatData.forEach(item => {
        if (item.parent_id === null) {
            projects.push(itemMap[item.id]);
        } else if (itemMap[item.parent_id]) {
            itemMap[item.parent_id].children.push(itemMap[item.id]);
        }
    });
    
    return projects;
}

function getMaxId(items) {
    let maxId = 0;
    items.forEach(item => {
        if (item.id > maxId) maxId = item.id;
        if (item.children && item.children.length > 0) {
            const childMaxId = getMaxId(item.children);
            if (childMaxId > maxId) maxId = childMaxId;
        }
    });
    return maxId;
}

function initializeEventListeners() {
    // Project form handlers
    document.getElementById('newProjectBtn').addEventListener('click', showProjectForm);
    document.getElementById('emptyStateBtn').addEventListener('click', showProjectForm);
    document.getElementById('cancelProjectBtn').addEventListener('click', hideProjectForm);
    document.getElementById('projectForm').addEventListener('submit', handleProjectSubmit);
    
    // Modal handlers
    document.getElementById('closeTaskModal').addEventListener('click', closeTaskModal);
    document.getElementById('taskModal').addEventListener('click', function(e) {
        if (e.target === this) closeTaskModal();
    });
    
    // Task action handlers
    document.getElementById('markCompleteBtn').addEventListener('click', markTaskComplete);
    document.getElementById('addSubtaskBtn').addEventListener('click', showSubtaskForm);
    document.getElementById('cancelSubtask').addEventListener('click', hideSubtaskForm);
    document.getElementById('addSubtaskSubmit').addEventListener('click', handleSubtaskSubmit);
    
    // Handle Enter key in subtask input
    document.getElementById('subtaskTitle').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleSubtaskSubmit();
        }
    });
}

function showProjectForm() {
    document.getElementById('projectFormContainer').classList.add('show');
    document.getElementById('projectTitle').focus();
}

function hideProjectForm() {
    document.getElementById('projectFormContainer').classList.remove('show');
    document.getElementById('projectForm').reset();
}

async function handleProjectSubmit(e) {
    e.preventDefault();
    
    const title = document.getElementById('projectTitle').value.trim();
    const description = document.getElementById('projectDescription').value.trim();
    
    if (!title) return;
    
    try {
        // Create project via API
        const response = await apiService.createProject({
            title: title,
            description: description,
            completed: false,
            parent_id: null
        });
        
        if (response.success && response.data) {
            // Add to local projects array with hierarchical structure
            const newProject = {
                ...response.data,
                children: []
            };
            
            projects.push(newProject);
            renderProjects();
            updateEmptyState();
            hideProjectForm();
            
            console.log('✅ Project created:', newProject);
            
            // Add fade-in animation to new project
            setTimeout(() => {
                const newProjectElement = document.querySelector(`[data-project-id="${newProject.id}"]`);
                if (newProjectElement) {
                    newProjectElement.classList.add('fade-in');
                }
            }, 100);
        }
    } catch (error) {
        console.error('❌ Failed to create project:', error);
        alert('Failed to create project. Please try again.');
    }
}

function renderProjects() {
    const projectsList = document.getElementById('projectsList');
    
    if (projects.length === 0) {
        projectsList.innerHTML = '';
        return;
    }
    
    projectsList.innerHTML = projects.map(project => renderProjectCard(project)).join('');
    
    // Add event listeners to project cards
    projects.forEach(project => {
        const projectElement = document.querySelector(`[data-project-id="${project.id}"]`);
        if (projectElement) {
            projectElement.addEventListener('click', () => openTaskModal(project.id));
        }
        
        // Add event listeners to task items
        addTaskEventListeners(project);
    });
}

function addTaskEventListeners(task) {
    const taskElement = document.querySelector(`[data-task-id="${task.id}"]`);
    if (taskElement && !task.completed) {
        taskElement.addEventListener('click', (e) => {
            e.stopPropagation();
            openTaskModal(task.id);
        });
    }
    
    if (task.children) {
        task.children.forEach(child => addTaskEventListeners(child));
    }
}

function renderProjectCard(project) {
    const progress = calculateProgress(project);
    const isComplete = project.completed;
    
    return `
        <div class="project-card" data-project-id="${project.id}">
            <div class="project-card__header">
                <div class="project-card__info">
                    <h3 class="project-card__title">${escapeHtml(project.title)}</h3>
                    ${project.description ? `<p class="project-card__description">${escapeHtml(project.description)}</p>` : ''}
                </div>
                <div class="project-card__status">
                    <div class="progress-circle ${isComplete ? 'progress-circle--complete' : 'progress-circle--incomplete'}">
                        ${isComplete ? '✓' : Math.round(progress) + '%'}
                    </div>
                </div>
            </div>
            ${project.children.length > 0 ? `
                <div class="project-card__body">
                    <div class="project-tree">
                        ${renderTaskTree(project.children)}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

function renderTaskTree(tasks) {
    return tasks.map(task => `
        <div class="task-item ${task.completed ? 'task-item--completed' : ''}" data-task-id="${task.id}">
            <div class="task-item__icon">
                ${task.completed ? '✓' : '○'}
            </div>
            <div class="task-item__text">${escapeHtml(task.title)}</div>
        </div>
        ${task.children.length > 0 ? `
            <div class="task-item__children">
                ${renderTaskTree(task.children)}
            </div>
        ` : ''}
    `).join('');
}

function calculateProgress(task) {
    if (!task.children || task.children.length === 0) {
        return task.completed ? 100 : 0;
    }
    
    const totalTasks = countAllTasks(task.children);
    const completedTasks = countCompletedTasks(task.children);
    
    return totalTasks > 0 ? (completedTasks / totalTasks) * 100 : 0;
}

function countAllTasks(tasks) {
    let count = 0;
    tasks.forEach(task => {
        count += 1;
        if (task.children && task.children.length > 0) {
            count += countAllTasks(task.children);
        }
    });
    return count;
}

function countCompletedTasks(tasks) {
    let count = 0;
    tasks.forEach(task => {
        if (task.completed) {
            count += 1;
        }
        if (task.children && task.children.length > 0) {
            count += countCompletedTasks(task.children);
        }
    });
    return count;
}

function updateEmptyState() {
    const emptyState = document.getElementById('emptyState');
    const projectsList = document.getElementById('projectsList');
    
    if (projects.length === 0) {
        emptyState.style.display = 'block';
        projectsList.style.display = 'none';
    } else {
        emptyState.style.display = 'none';
        projectsList.style.display = 'block';
    }
}

function findTaskById(taskId, taskList = projects) {
    for (let task of taskList) {
        if (task.id === taskId) {
            return task;
        }
        if (task.children && task.children.length > 0) {
            const found = findTaskById(taskId, task.children);
            if (found) return found;
        }
    }
    return null;
}

function openTaskModal(taskId) {
    currentTaskId = taskId;
    const task = findTaskById(taskId);
    
    if (!task) return;
    
    document.getElementById('taskModalTitle').textContent = task.title;
    
    // Show/hide elements based on task completion
    const taskQuestion = document.querySelector('.task-question');
    const markCompleteBtn = document.getElementById('markCompleteBtn');
    const addSubtaskBtn = document.getElementById('addSubtaskBtn');
    
    // Reset button state for new task
    markCompleteBtn.textContent = 'Yes, it\'s done!';
    markCompleteBtn.disabled = false;
    
    // Always show the question unless explicitly completed
    // This ensures that even 100% progress tasks still ask "Is it done yet?"
    if (task.completed) {
        taskQuestion.style.display = 'none';
    } else {
        taskQuestion.style.display = 'block';
        markCompleteBtn.style.display = 'flex';
        addSubtaskBtn.style.display = 'flex';
    }
    
    // Render progress and subtasks
    renderTaskProgress(task);
    renderSubtasks(task);
    
    // Show modal
    document.getElementById('taskModal').classList.add('show');
}

function closeTaskModal() {
    const modal = document.getElementById('taskModal');
    
    // Remove the show class to hide modal
    modal.classList.remove('show');
    
    // Reset all form states
    hideSubtaskForm();
    
    // Reset button states
    const markCompleteBtn = document.getElementById('markCompleteBtn');
    markCompleteBtn.textContent = 'Yes, it\'s done!';
    markCompleteBtn.disabled = false;
    
    const addSubtaskBtn = document.getElementById('addSubtaskSubmit');
    addSubtaskBtn.textContent = 'Add Subtask';
    addSubtaskBtn.disabled = false;
    
    // Clear current task
    currentTaskId = null;
    
    // Ensure modal is fully hidden after animation
    setTimeout(() => {
        modal.style.display = '';
    }, 300);
}

function renderTaskProgress(task) {
    const progressContainer = document.getElementById('taskProgress');
    
    if (!task.children || task.children.length === 0) {
        progressContainer.innerHTML = '';
        return;
    }
    
    const progress = calculateProgress(task);
    const totalTasks = countAllTasks(task.children);
    const completedTasks = countCompletedTasks(task.children);
    
    progressContainer.innerHTML = `
        <div class="progress-bar">
            <div class="progress-bar__fill" style="width: ${progress}%"></div>
        </div>
        <div class="progress-text">
            ${completedTasks} of ${totalTasks} subtasks completed (${Math.round(progress)}%)
        </div>
    `;
}

function renderSubtasks(task) {
    const subtasksList = document.getElementById('subtasksList');
    
    if (!task.children || task.children.length === 0) {
        subtasksList.innerHTML = '';
        return;
    }
    
    subtasksList.innerHTML = `
        <h4>Subtasks</h4>
        ${task.children.map(subtask => `
            <div class="subtask-item ${subtask.completed ? 'subtask-item--completed' : ''}" data-subtask-id="${subtask.id}">
                <div class="subtask-item__icon">
                    ${subtask.completed ? '✓' : '○'}
                </div>
                <div class="subtask-item__text">${escapeHtml(subtask.title)}</div>
                ${subtask.children.length > 0 ? `
                    <div class="subtask-item__progress">
                        ${Math.round(calculateProgress(subtask))}%
                    </div>
                ` : ''}
            </div>
        `).join('')}
    `;
    
    // Add click listeners to subtasks
    task.children.forEach(subtask => {
        const subtaskElement = document.querySelector(`[data-subtask-id="${subtask.id}"]`);
        if (subtaskElement) {
            subtaskElement.addEventListener('click', () => {
                closeTaskModal();
                setTimeout(() => openTaskModal(subtask.id), 100);
            });
        }
    });
}

async function markTaskComplete() {
    const task = findTaskById(currentTaskId);
    if (!task) return;
    
    // Show immediate visual feedback
    const markCompleteBtn = document.getElementById('markCompleteBtn');
    markCompleteBtn.textContent = '✓ Marking Complete...';
    markCompleteBtn.disabled = true;
    
    try {
        // Mark complete via API
        const response = await apiService.markProjectComplete(currentTaskId);
        
        if (response.success) {
            setTimeout(() => {
                task.completed = true;
                
                // Animate completion with visual feedback
                animateTaskCompletion(task);
                
                // Check if parent should be marked complete and animate propagation
                const completedParents = updateParentCompletion(task);
                
                // Show propagation animation
                if (completedParents.length > 0) {
                    showCompletionPropagation(completedParents);
                }
                
                // Re-render everything with animations
                renderProjectsWithAnimation();
                
                // Show completion message
                showCompletionMessage(task, completedParents);
                
                // Close the modal after completion
                closeTaskModal();
                
                console.log('✅ Task marked complete:', task);
            }, 300);
        }
    } catch (error) {
        console.error('❌ Failed to mark task complete:', error);
        markCompleteBtn.textContent = 'Yes, it\'s done!';
        markCompleteBtn.disabled = false;
        alert('Failed to mark task complete. Please try again.');
    }
}

function animateTaskCompletion(task) {
    // Find the task element in the main view
    const taskElement = document.querySelector(`[data-task-id="${task.id}"]`);
    if (taskElement) {
        taskElement.style.transition = 'all 0.5s ease';
        taskElement.style.background = 'rgba(var(--color-success-rgb), 0.2)';
        
        setTimeout(() => {
            taskElement.classList.add('task-item--completed');
            taskElement.style.background = '';
        }, 500);
    }
}

function updateParentCompletion(task) {
    // Note: We don't automatically mark parents as complete
    // The user must explicitly mark each level as complete by answering "Is it done yet?"
    // This preserves the core principle of the app - even if all subtasks are done,
    // there might be more work to discover or the parent task might not truly be complete
    return [];
}

function showCompletionPropagation(completedParents) {
    completedParents.forEach((parent, index) => {
        setTimeout(() => {
            const parentElement = document.querySelector(`[data-task-id="${parent.id}"]`);
            if (parentElement) {
                parentElement.style.transition = 'all 0.3s ease';
                parentElement.style.background = 'rgba(var(--color-success-rgb), 0.3)';
                parentElement.style.transform = 'scale(1.02)';
                
                setTimeout(() => {
                    parentElement.classList.add('task-item--completed');
                    parentElement.style.background = '';
                    parentElement.style.transform = '';
                }, 300);
            }
            
            // Update progress circles with animation
            const progressCircle = document.querySelector(`[data-project-id="${parent.id}"] .progress-circle`);
            if (progressCircle) {
                progressCircle.style.transition = 'all 0.5s ease';
                progressCircle.classList.remove('progress-circle--incomplete');
                progressCircle.classList.add('progress-circle--complete');
                progressCircle.innerHTML = '✓';
                progressCircle.style.transform = 'scale(1.2)';
                
                setTimeout(() => {
                    progressCircle.style.transform = '';
                }, 500);
            }
        }, index * 200);
    });
}

function showCompletionMessage(task, completedParents) {
    let message = `✅ "${task.title}" completed!`;
    
    if (completedParents.length > 0) {
        const parentTitles = completedParents.map(p => `"${p.title}"`).join(', ');
        message += `\n🎉 This also completed: ${parentTitles}`;
    }
    
    // Create a temporary toast notification
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--color-success);
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: var(--shadow-lg);
        z-index: 1001;
        max-width: 300px;
        white-space: pre-line;
        animation: slideIn 0.3s ease;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

function renderProjectsWithAnimation() {
    // Store current scroll position
    const scrollTop = window.pageYOffset;
    
    renderProjects();
    
    // Restore scroll position
    window.scrollTo(0, scrollTop);
    
    // Add animation to updated elements
    setTimeout(() => {
        const updatedElements = document.querySelectorAll('.task-item--completed, .progress-circle--complete');
        updatedElements.forEach(el => {
            if (!el.classList.contains('animated')) {
                el.classList.add('checkmark-animation', 'animated');
            }
        });
    }, 100);
}

function showSubtaskForm() {
    document.getElementById('subtaskForm').style.display = 'block';
    document.getElementById('subtaskTitle').focus();
}

function hideSubtaskForm() {
    const subtaskForm = document.getElementById('subtaskForm');
    const subtaskTitle = document.getElementById('subtaskTitle');
    const submitBtn = document.getElementById('addSubtaskSubmit');
    
    // Hide the form
    subtaskForm.style.display = 'none';
    
    // Clear the input
    subtaskTitle.value = '';
    
    // Reset button state
    submitBtn.textContent = 'Add Subtask';
    submitBtn.disabled = false;
}

async function handleSubtaskSubmit() {
    const title = document.getElementById('subtaskTitle').value.trim();
    if (!title) return;
    
    const parentTask = findTaskById(currentTaskId);
    if (!parentTask) return;
    
    // Show visual feedback
    const submitBtn = document.getElementById('addSubtaskSubmit');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Adding...';
    submitBtn.disabled = true;
    
    try {
        // Create subtask via API
        const response = await apiService.addSubtask(currentTaskId, {
            title: title,
            description: '',
            completed: false,
            parent_id: currentTaskId
        });
          if (response.success && response.data) {
            const newSubtask = {
                ...response.data,
                children: []
            };
            
            parentTask.children.push(newSubtask);
            
            // Re-render everything
            renderProjects();
            
            // Reset button states
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            
            console.log('✅ Subtask created:', newSubtask);
            
            // Show success message
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--color-success, #28a745);
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 1001;
                animation: slideIn 0.3s ease;
            `;
            toast.textContent = `✅ Subtask "${title}" added!`;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 2000);
            
            // Close current modal and open the new subtask modal
            closeTaskModal();
            setTimeout(() => {
                openTaskModal(newSubtask.id);
            }, 100);
        }
    } catch (error) {
        console.error('❌ Failed to create subtask:', error);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        alert('Failed to create subtask. Please try again.');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS animations dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);