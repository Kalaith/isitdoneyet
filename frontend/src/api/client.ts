import axios, { type AxiosError } from "axios";
import { env } from "../config/env";
import type {
  AgentDoneCheck,
  AgentTokenResponse,
  ApiResponse,
  CurrentUser,
  Project,
} from "../types/project";

type TokenResolver = () => string | null;
type UnauthorizedCallback = (loginUrl: string | null) => void;

let tokenResolver: TokenResolver | null = null;
let unauthorizedCallback: UnauthorizedCallback | null = null;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly loginUrl: string | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export function registerAuthTokenResolver(resolver: TokenResolver): void {
  tokenResolver = resolver;
}

export function registerUnauthorizedCallback(
  callback: UnauthorizedCallback,
): void {
  unauthorizedCallback = callback;
}

const api = axios.create({
  baseURL: env.apiBaseUrl,
  headers: {
    "Content-Type": "application/json",
  },
});

api.interceptors.request.use((config) => {
  const token = tokenResolver?.() ?? null;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiResponse<unknown>>) => {
    const status = error.response?.status ?? 500;
    const payload = error.response?.data;
    const loginUrl = payload?.login_url ?? null;
    const message = payload?.message ?? error.message;

    if (status === 401) {
      unauthorizedCallback?.(loginUrl);
    }

    throw new ApiError(message, status, loginUrl);
  },
);

async function request<T>(
  path: string,
  method: "GET" | "POST" | "PUT" | "DELETE" = "GET",
  data?: unknown,
): Promise<ApiResponse<T>> {
  const response = await api.request<ApiResponse<T>>({
    url: path,
    method,
    data,
  });

  const payload = response.data;

  if (!payload.success) {
    throw new ApiError(
      payload.message ?? "API request failed",
      response.status,
    );
  }

  return payload;
}

export function getProjects(): Promise<ApiResponse<Project[]>> {
  return request<Project[]>("/projects");
}

export function getCurrentUser(): Promise<ApiResponse<CurrentUser>> {
  return request<CurrentUser>("/me");
}

export function createProject(input: {
  title: string;
  description: string;
}): Promise<ApiResponse<Project>> {
  return request<Project>("/projects", "POST", input);
}

export function markProjectComplete(
  id: number,
): Promise<ApiResponse<{ completed_projects: Project[] }>> {
  return request<{ completed_projects: Project[] }>(
    `/projects/${id}/complete`,
    "POST",
  );
}

export function addSubtask(
  parentId: number,
  input: { title: string; description: string },
): Promise<ApiResponse<Project>> {
  return request<Project>(`/projects/${parentId}/subtasks`, "POST", input);
}

export function getAgentDoneCheck(
  projectId: number,
): Promise<ApiResponse<AgentDoneCheck>> {
  return request<AgentDoneCheck>(`/agent/projects/${projectId}/done-check`);
}

export function createAgentToken(input: {
  agent_name: string;
  project_id?: number;
  expires_in_seconds: number;
}): Promise<ApiResponse<AgentTokenResponse>> {
  return request<AgentTokenResponse>("/agent/tokens", "POST", input);
}

export function deleteProject(id: number): Promise<ApiResponse<null>> {
  return request<null>(`/projects/${id}`, "DELETE");
}
