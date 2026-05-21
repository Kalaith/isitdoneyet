import { useCallback, useEffect, useMemo, useState } from "react";
import type { FC, FormEvent, ReactElement } from "react";
import {
  addSubtask,
  createAgentToken,
  createProject,
  deleteProject,
  getAgentDoneCheck,
  getCurrentUser,
  getProjects,
  markProjectComplete,
} from "./api/client";
import { useAuthStore } from "./stores/useAuthStore";
import type { AuthUser } from "./stores/useAuthStore";
import type {
  AgentDoneCheck,
  AgentTaskSummary,
  AgentTokenResponse,
  Project,
} from "./types/project";

interface AgentStatusState {
  error: string | null;
  loading: boolean;
  status: AgentDoneCheck | null;
}

interface ProjectNodeProps {
  project: Project;
  depth?: number;
  onComplete: (id: number) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
  onAddSubtask: (
    id: number,
    title: string,
    description: string,
  ) => Promise<void>;
  onAgentCheck: (id: number) => Promise<void>;
  agentStatuses: Record<number, AgentStatusState>;
  busyProjectId: number | null;
}

const maxDepthClass = 6;

const hasIncompleteDescendants = (project: Project): boolean =>
  project.children.some(
    (child) => !child.completed || hasIncompleteDescendants(child),
  );

const formatNextAction = (action: AgentDoneCheck["next_action"]): string => {
  switch (action) {
    case "work":
      return "Work";
    case "reassess":
      return "Reassess";
    case "breakdown":
      return "Break Down";
    case "done":
      return "Done";
  }
};

const taskListLabel = (status: AgentDoneCheck): string => {
  if (status.next_tasks.length > 0) {
    return "Next Tasks";
  }

  if (status.reassessment_questions.length > 0) {
    return "Reassess";
  }

  if (status.why_not.length > 0) {
    return "Why Not";
  }

  return "Complete";
};

const visibleAgentTasks = (status: AgentDoneCheck): AgentTaskSummary[] => {
  if (status.next_tasks.length > 0) {
    return status.next_tasks;
  }

  if (status.reassessment_questions.length > 0) {
    return status.reassessment_questions;
  }

  return status.why_not;
};

const signedInLabel = (user: AuthUser | null): string | null => {
  if (!user?.id) {
    return null;
  }

  const userLabel = user.display_name || user.username || user.id;
  if (user.actor_type === "agent" && user.agent_name) {
    return `${user.agent_name} (agent for ${userLabel})`;
  }

  return userLabel;
};

const projectStatusLabel = (project: Project): string => {
  if (project.completed) {
    return "Complete";
  }

  return hasIncompleteDescendants(project) ? "Blocked" : "Open";
};

const projectTaskCount = (project: Project): number =>
  1 +
  project.children.reduce((total, child) => total + projectTaskCount(child), 0);

const ProjectTreeMapNode: FC<{ project: Project }> = ({
  project,
}): ReactElement => (
  <li className={project.children.length > 0 ? "has-children" : undefined}>
    <div
      className={`tree-node ${project.completed ? "is-complete" : ""}`}
      role="treeitem"
    >
      <span>{project.title}</span>
      <small>
        {projectStatusLabel(project)} - {project.progress}%
      </small>
    </div>
    {project.children.length > 0 && (
      <ul role="group">
        {project.children.map((child) => (
          <ProjectTreeMapNode key={child.id} project={child} />
        ))}
      </ul>
    )}
  </li>
);

const ProjectTreeMap: FC<{ project: Project }> = ({
  project,
}): ReactElement => (
  <section
    aria-label={`Project tree for ${project.title}`}
    className="tree-map"
  >
    <div className="tree-map-header">
      <h4>Tree View</h4>
      <span>{projectTaskCount(project)} tasks</span>
    </div>
    <div className="tree-map-scroll">
      <ul className="tree-root" role="tree">
        <ProjectTreeMapNode project={project} />
      </ul>
    </div>
  </section>
);

const ProjectNode: FC<ProjectNodeProps> = ({
  project,
  onComplete,
  onDelete,
  onAddSubtask,
  onAgentCheck,
  agentStatuses,
  busyProjectId,
  depth = 0,
}): ReactElement => {
  const [showSubtaskForm, setShowSubtaskForm] = useState(false);
  const [showTreeView, setShowTreeView] = useState(project.children.length > 0);
  const [subtaskTitle, setSubtaskTitle] = useState("");
  const [subtaskDescription, setSubtaskDescription] = useState("");

  const isBusy = busyProjectId === project.id;
  const depthClass = `depth-${Math.min(depth, maxDepthClass)}`;
  const hasOpenChildTasks = hasIncompleteDescendants(project);
  const agentStatus = agentStatuses[project.id];

  useEffect(() => {
    if (project.children.length > 0) {
      setShowTreeView(true);
    }
  }, [project.children.length]);

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
    <li className={`project-node ${depthClass}`}>
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
              disabled={isBusy || hasOpenChildTasks}
              onClick={() => onComplete(project.id)}
              title={
                hasOpenChildTasks
                  ? "Complete child tasks before marking this task complete."
                  : undefined
              }
              type="button"
            >
              Mark Complete
            </button>
          )}
          <button
            disabled={isBusy || agentStatus?.loading}
            onClick={() => onAgentCheck(project.id)}
            type="button"
          >
            Check Status
          </button>
          <button
            aria-expanded={showTreeView}
            disabled={isBusy}
            onClick={() => setShowTreeView((value) => !value)}
            type="button"
          >
            {showTreeView ? "Hide Tree" : "View Tree"}
          </button>
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
        {showTreeView && <ProjectTreeMap project={project} />}
        {agentStatus?.status && (
          <section className="agent-status" aria-label="Agent Status">
            <div className="agent-status-title">Agent Status</div>
            <div className="agent-status-summary">
              <span
                className={`agent-pill action-${agentStatus.status.next_action}`}
              >
                {formatNextAction(agentStatus.status.next_action)}
              </span>
              <span>{agentStatus.status.answer === "yes" ? "Yes" : "No"}</span>
              <span>{agentStatus.status.counts.open} open</span>
            </div>
            <div className="agent-status-detail">
              <span>{taskListLabel(agentStatus.status)}</span>
              <ul>
                {visibleAgentTasks(agentStatus.status)
                  .slice(0, 4)
                  .map((task) => (
                    <li key={task.id}>
                      <span>{task.title}</span>
                      {task.reason && <small>{task.reason}</small>}
                    </li>
                  ))}
                {visibleAgentTasks(agentStatus.status).length === 0 && (
                  <li>All tracked work is complete.</li>
                )}
              </ul>
            </div>
          </section>
        )}
        {agentStatus?.error && (
          <p className="agent-error">{agentStatus.error}</p>
        )}
      </div>
      {project.children.length > 0 && (
        <ul className="project-list children">
          {project.children.map((child) => (
            <ProjectNode
              agentStatuses={agentStatuses}
              busyProjectId={busyProjectId}
              depth={depth + 1}
              key={child.id}
              onAddSubtask={onAddSubtask}
              onAgentCheck={onAgentCheck}
              onComplete={onComplete}
              onDelete={onDelete}
              project={child}
            />
          ))}
        </ul>
      )}
    </li>
  );
};

const App: FC = (): ReactElement => {
  const { token, loginUrl, user, setAuth } = useAuthStore();
  const [projects, setProjects] = useState<Project[]>([]);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [loading, setLoading] = useState(Boolean(token));
  const [error, setError] = useState<string | null>(null);
  const [busyProjectId, setBusyProjectId] = useState<number | null>(null);
  const [agentName, setAgentName] = useState("Codex");
  const [assignedProjectId, setAssignedProjectId] = useState("");
  const [agentProjectTitle, setAgentProjectTitle] = useState("");
  const [agentToken, setAgentToken] = useState<AgentTokenResponse | null>(null);
  const [agentTokenError, setAgentTokenError] = useState<string | null>(null);
  const [agentTokenBusy, setAgentTokenBusy] = useState(false);
  const [agentStatuses, setAgentStatuses] = useState<
    Record<number, AgentStatusState>
  >({});

  const refreshAgentStatus = useCallback(async (projectId: number) => {
    setAgentStatuses((current) => ({
      ...current,
      [projectId]: {
        error: null,
        loading: true,
        status: current[projectId]?.status ?? null,
      },
    }));

    try {
      const response = await getAgentDoneCheck(projectId);
      setAgentStatuses((current) => ({
        ...current,
        [projectId]: {
          error: null,
          loading: false,
          status: response.data ?? null,
        },
      }));
    } catch (err) {
      const message =
        err instanceof Error ? err.message : "Failed to check agent status";
      setAgentStatuses((current) => ({
        ...current,
        [projectId]: {
          error: message,
          loading: false,
          status: current[projectId]?.status ?? null,
        },
      }));
    }
  }, []);

  const refreshRootAgentStatuses = useCallback(
    async (nextProjects: Project[]) => {
      if (nextProjects.length === 0) {
        setAgentStatuses({});
        return;
      }

      await Promise.all(
        nextProjects.map((project) => refreshAgentStatus(project.id)),
      );
    },
    [refreshAgentStatus],
  );

  const loadProjects = useCallback(async () => {
    if (!token) {
      setProjects([]);
      setAgentStatuses({});
      setLoading(false);
      return;
    }

    setError(null);
    const currentUser = await getCurrentUser();
    if (currentUser.data) {
      setAuth(
        {
          id: currentUser.data.id,
          actor_type: currentUser.data.actor_type ?? undefined,
          agent_name: currentUser.data.agent_name ?? undefined,
          assigned_project_id:
            currentUser.data.assigned_project_id ?? undefined,
          username: currentUser.data.username ?? undefined,
          email: currentUser.data.email ?? undefined,
          display_name: currentUser.data.display_name ?? undefined,
          role: currentUser.data.role ?? undefined,
        },
        token,
      );
    }

    const response = await getProjects();
    const nextProjects = response.data ?? [];
    setProjects(nextProjects);
    await refreshRootAgentStatuses(nextProjects);
  }, [refreshRootAgentStatuses, setAuth, token]);

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(Boolean(token));
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
  }, [loadProjects, token]);

  const handleCreateProject = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const normalizedTitle = title.trim();
    if (!normalizedTitle || !token) {
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

  const normalizedAgentName = (): string => agentName.trim() || "Codex";

  const handleCreateAgentToken = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!token) {
      return;
    }

    try {
      setAgentTokenBusy(true);
      setAgentTokenError(null);
      const projectId =
        assignedProjectId === "" ? undefined : Number(assignedProjectId);
      const response = await createAgentToken({
        agent_name: normalizedAgentName(),
        project_id: projectId,
        expires_in_seconds: 86400,
      });
      setAgentToken(response.data ?? null);
    } catch (err) {
      const message =
        err instanceof Error ? err.message : "Failed to create agent token";
      setAgentTokenError(message);
    } finally {
      setAgentTokenBusy(false);
    }
  };

  const handleCreateAgentProject = async (
    event: FormEvent<HTMLFormElement>,
  ) => {
    event.preventDefault();
    const normalizedTitle = agentProjectTitle.trim();
    if (!token || normalizedTitle === "") {
      return;
    }

    try {
      setAgentTokenBusy(true);
      setAgentTokenError(null);
      const projectResponse = await createProject({
        title: normalizedTitle,
        description: `Assigned to ${normalizedAgentName()} for agent-driven tracking.`,
      });
      if (!projectResponse.data) {
        throw new Error("Project was created without a response body.");
      }

      const tokenResponse = await createAgentToken({
        agent_name: normalizedAgentName(),
        project_id: projectResponse.data.id,
        expires_in_seconds: 86400,
      });

      setAgentProjectTitle("");
      setAssignedProjectId(String(projectResponse.data.id));
      setAgentToken(tokenResponse.data ?? null);
      await loadProjects();
    } catch (err) {
      const message =
        err instanceof Error ? err.message : "Failed to create agent project";
      setAgentTokenError(message);
    } finally {
      setAgentTokenBusy(false);
    }
  };

  const totalProjects = useMemo(() => projects.length, [projects]);
  const currentSignedInLabel = signedInLabel(user);

  return (
    <main className="app-shell">
      <section className="app-card">
        <header>
          <h1>Is It Done Yet?</h1>
          <p className="meta">
            Root projects: {totalProjects}
            {token && currentSignedInLabel
              ? ` | Signed in as ${currentSignedInLabel}`
              : ""}
          </p>
        </header>

        {!token && (
          <div className="auth-required">
            <p>Sign in with WebHatchery to manage your projects.</p>
            <a href={loginUrl}>Open WebHatchery Login</a>
          </div>
        )}

        <form className="create-form" onSubmit={handleCreateProject}>
          <input
            disabled={!token}
            onChange={(event) => setTitle(event.target.value)}
            placeholder="Project title"
            required
            type="text"
            value={title}
          />
          <input
            disabled={!token}
            onChange={(event) => setDescription(event.target.value)}
            placeholder="Description (optional)"
            type="text"
            value={description}
          />
          <button disabled={!token} type="submit">
            Create Project
          </button>
        </form>

        {token && (
          <section className="agent-access" aria-label="Agent Access">
            <div className="agent-access-header">
              <h2>Agent Access</h2>
              <span>Tokens act as user {user?.id}</span>
            </div>
            <form
              className="agent-token-form"
              onSubmit={handleCreateAgentToken}
            >
              <input
                onChange={(event) => setAgentName(event.target.value)}
                placeholder="Agent name"
                type="text"
                value={agentName}
              />
              <select
                onChange={(event) => setAssignedProjectId(event.target.value)}
                value={assignedProjectId}
              >
                <option value="">No project assignment</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.title}
                  </option>
                ))}
              </select>
              <button disabled={agentTokenBusy} type="submit">
                Create Agent Token
              </button>
            </form>
            <form
              className="agent-token-form"
              onSubmit={handleCreateAgentProject}
            >
              <input
                onChange={(event) => setAgentProjectTitle(event.target.value)}
                placeholder="New project for agent"
                type="text"
                value={agentProjectTitle}
              />
              <button
                disabled={agentTokenBusy || agentProjectTitle.trim() === ""}
                type="submit"
              >
                Create Project & Agent Token
              </button>
            </form>
            {agentTokenError && (
              <p className="agent-error">{agentTokenError}</p>
            )}
            {agentToken && (
              <div className="agent-token-result">
                <p>
                  {agentToken.agent_name} token for user {agentToken.owner_id}
                  {agentToken.assigned_project_id
                    ? `, project ${agentToken.assigned_project_id}`
                    : ""}
                  . Expires {agentToken.expires_at}.
                </p>
                <textarea
                  aria-label="Agent token"
                  readOnly
                  value={agentToken.token}
                />
              </div>
            )}
          </section>
        )}

        {error && <p className="error">{error}</p>}
        {loading && <p className="status">Loading projects...</p>}

        {token && !loading && (
          <ul className="project-list">
            {projects.map((project) => (
              <ProjectNode
                agentStatuses={agentStatuses}
                busyProjectId={busyProjectId}
                key={project.id}
                onAddSubtask={handleAddSubtask}
                onAgentCheck={refreshAgentStatus}
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
};

export default App;
