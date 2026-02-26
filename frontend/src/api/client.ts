import type { ApiResponse, Project } from "../types/project";

const DEFAULT_API_BASE_URL = "/api";

const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL ?? DEFAULT_API_BASE_URL
).replace(/\/+$/, "");

async function request<T>(
  path: string,
  init: RequestInit = {},
): Promise<ApiResponse<T>> {
  const response = await fetch(`${apiBaseUrl}${path}`, {
    headers: {
      "Content-Type": "application/json",
      ...init.headers,
    },
    ...init,
  });

  const payload = (await response.json()) as ApiResponse<T>;

  if (!response.ok) {
    throw new Error(
      payload.message ?? `Request failed with ${response.status}`,
    );
  }

  if (!payload.success) {
    throw new Error(payload.message ?? "API request failed");
  }

  return payload;
}

export function getProjects(): Promise<ApiResponse<Project[]>> {
  return request<Project[]>("/projects");
}

export function createProject(input: {
  title: string;
  description: string;
}): Promise<ApiResponse<Project>> {
  return request<Project>("/projects", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function markProjectComplete(
  id: number,
): Promise<ApiResponse<{ completed_projects: Project[] }>> {
  return request<{ completed_projects: Project[] }>(
    `/projects/${id}/complete`,
    {
      method: "POST",
    },
  );
}

export function addSubtask(
  parentId: number,
  input: { title: string; description: string },
): Promise<ApiResponse<Project>> {
  return request<Project>(`/projects/${parentId}/subtasks`, {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function deleteProject(id: number): Promise<ApiResponse<null>> {
  return request<null>(`/projects/${id}`, {
    method: "DELETE",
  });
}
