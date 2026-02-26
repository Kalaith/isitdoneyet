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

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
}
