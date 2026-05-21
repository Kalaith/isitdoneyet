export interface Project {
  id: number;
  title: string;
  description: string;
  completed: boolean;
  parent_id: number | null;
  created_at: string;
  updated_at: string;
  progress: number;
  children: Project[];
}

export interface CurrentUser {
  id: string;
  actor_type?: string | null;
  agent_name?: string | null;
  assigned_project_id?: number | null;
  username?: string | null;
  display_name?: string | null;
  email?: string | null;
  role?: string | null;
}

export interface AgentTokenResponse {
  token: string;
  token_type: "Bearer";
  owner_id: string;
  actor_type: "agent";
  agent_name: string;
  assigned_project_id: number | null;
  scope: string;
  expires_at: string;
}

export interface AgentTaskSummary {
  id: number;
  title: string;
  description: string;
  parent_id: number | null;
  completed: boolean;
  progress: number;
  question?: string;
  reason?: string;
}

export interface AgentDoneCheck {
  project_id: number;
  project_title: string;
  question: string;
  answer: "yes" | "no";
  is_done: boolean;
  progress: number;
  counts: {
    total: number;
    completed: number;
    open: number;
  };
  next_action: "work" | "reassess" | "breakdown" | "done";
  why_not: AgentTaskSummary[];
  next_tasks: AgentTaskSummary[];
  reassessment_questions: AgentTaskSummary[];
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  login_url?: string;
}
