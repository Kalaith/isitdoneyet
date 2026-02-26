import { useCallback, useEffect, useMemo, useState } from "react";
import type { FormEvent } from "react";
import {
  addSubtask,
  createProject,
  deleteProject,
  getProjects,
  markProjectComplete,
} from "./api/client";
import type { Project } from "./types/project";

function ProjectNode(props: {
  project: Project;
  depth?: number;
  onComplete: (id: number) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
  onAddSubtask: (
    id: number,
    title: string,
    description: string,
  ) => Promise<void>;
  busyProjectId: number | null;
}) {
  const {
    project,
    onComplete,
    onDelete,
    onAddSubtask,
    busyProjectId,
    depth = 0,
  } = props;
  const [showSubtaskForm, setShowSubtaskForm] = useState(false);
  const [subtaskTitle, setSubtaskTitle] = useState("");
  const [subtaskDescription, setSubtaskDescription] = useState("");

  const isBusy = busyProjectId === project.id;

  const handleSubmitSubtask = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const title = subtaskTitle.trim();
    if (!title) {
      return;
    }

    await onAddSubtask(project.id, title, subtaskDescription.trim());
    setSubtaskTitle("");
    setSubtaskDescription("");
    setShowSubtaskForm(false);
  };

  return (
    <li className="project-node" style={{ marginLeft: `${depth * 1}rem` }}>
      <div className={`project-card ${project.completed ? "is-complete" : ""}`}>
        <div className="project-header">
          <h3>{project.title}</h3>
          <span className="project-progress">{project.progress}%</span>
        </div>
        {project.description && (
          <p className="project-description">{project.description}</p>
        )}
        <div className="project-actions">
          {!project.completed && (
            <button
              disabled={isBusy}
              onClick={() => onComplete(project.id)}
              type="button"
            >
              Mark Complete
            </button>
          )}
          <button
            disabled={isBusy}
            onClick={() => setShowSubtaskForm((value) => !value)}
            type="button"
          >
            Add Subtask
          </button>
          <button
            className="danger"
            disabled={isBusy}
            onClick={() => onDelete(project.id)}
            type="button"
          >
            Delete
          </button>
        </div>
        {showSubtaskForm && (
          <form className="subtask-form" onSubmit={handleSubmitSubtask}>
            <input
              onChange={(event) => setSubtaskTitle(event.target.value)}
              placeholder="Subtask title"
              required
              type="text"
              value={subtaskTitle}
            />
            <input
              onChange={(event) => setSubtaskDescription(event.target.value)}
              placeholder="Subtask description (optional)"
              type="text"
              value={subtaskDescription}
            />
            <button disabled={isBusy} type="submit">
              Save Subtask
            </button>
          </form>
        )}
      </div>
      {project.children.length > 0 && (
        <ul className="project-list children">
          {project.children.map((child) => (
            <ProjectNode
              busyProjectId={busyProjectId}
              depth={depth + 1}
              key={child.id}
              onAddSubtask={onAddSubtask}
              onComplete={onComplete}
              onDelete={onDelete}
              project={child}
            />
          ))}
        </ul>
      )}
    </li>
  );
}

function App() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyProjectId, setBusyProjectId] = useState<number | null>(null);

  const loadProjects = useCallback(async () => {
    setError(null);
    const response = await getProjects();
    setProjects(response.data ?? []);
  }, []);

  useEffect(() => {
    const load = async () => {
      try {
        await loadProjects();
      } catch (err) {
        const message =
          err instanceof Error ? err.message : "Failed to load projects";
        setError(message);
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, [loadProjects]);

  const handleCreateProject = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedTitle = title.trim();
    if (!normalizedTitle) {
      return;
    }

    try {
      setError(null);
      await createProject({
        title: normalizedTitle,
        description: description.trim(),
      });
      setTitle("");
      setDescription("");
      await loadProjects();
    } catch (err) {
      const message =
        err instanceof Error ? err.message : "Failed to create project";
      setError(message);
    }
  };

  const handleComplete = useCallback(
    async (id: number) => {
      try {
        setBusyProjectId(id);
        setError(null);
        await markProjectComplete(id);
        await loadProjects();
      } catch (err) {
        const message =
          err instanceof Error ? err.message : "Failed to mark complete";
        setError(message);
      } finally {
        setBusyProjectId(null);
      }
    },
    [loadProjects],
  );

  const handleDelete = useCallback(
    async (id: number) => {
      try {
        setBusyProjectId(id);
        setError(null);
        await deleteProject(id);
        await loadProjects();
      } catch (err) {
        const message =
          err instanceof Error ? err.message : "Failed to delete project";
        setError(message);
      } finally {
        setBusyProjectId(null);
      }
    },
    [loadProjects],
  );

  const handleAddSubtask = useCallback(
    async (id: number, subtaskTitle: string, subtaskDescription: string) => {
      try {
        setBusyProjectId(id);
        setError(null);
        await addSubtask(id, {
          title: subtaskTitle,
          description: subtaskDescription,
        });
        await loadProjects();
      } catch (err) {
        const message =
          err instanceof Error ? err.message : "Failed to add subtask";
        setError(message);
      } finally {
        setBusyProjectId(null);
      }
    },
    [loadProjects],
  );

  const totalProjects = useMemo(() => projects.length, [projects]);

  return (
    <main className="app-shell">
      <section className="app-card">
        <header>
          <h1>Is It Done Yet?</h1>
          <p>
            Recursive project tracking powered by the shared WebHatchery React
            frontend stack.
          </p>
          <p className="meta">Root projects: {totalProjects}</p>
        </header>

        <form className="create-form" onSubmit={handleCreateProject}>
          <input
            onChange={(event) => setTitle(event.target.value)}
            placeholder="Project title"
            required
            type="text"
            value={title}
          />
          <input
            onChange={(event) => setDescription(event.target.value)}
            placeholder="Description (optional)"
            type="text"
            value={description}
          />
          <button type="submit">Create Project</button>
        </form>

        {error && <p className="error">{error}</p>}
        {loading && <p className="status">Loading projects...</p>}

        {!loading && (
          <ul className="project-list">
            {projects.map((project) => (
              <ProjectNode
                busyProjectId={busyProjectId}
                key={project.id}
                onAddSubtask={handleAddSubtask}
                onComplete={handleComplete}
                onDelete={handleDelete}
                project={project}
              />
            ))}
            {projects.length === 0 && (
              <li className="status">No projects yet.</li>
            )}
          </ul>
        )}
      </section>
    </main>
  );
}

export default App;
