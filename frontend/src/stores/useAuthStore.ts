import { create } from "zustand";
import {
  registerAuthTokenResolver,
  registerUnauthorizedCallback,
} from "../api/client";
import { env } from "../config/env";

const FRONTPAGE_AUTH_STORAGE_KEY = "auth-storage";

export interface AuthUser {
  id: string;
  actor_type?: string;
  agent_name?: string;
  assigned_project_id?: number;
  username?: string;
  email?: string;
  display_name?: string;
  role?: string;
}

interface AuthState {
  user: AuthUser | null;
  token: string | null;
  loginUrl: string;
  setAuth: (user: AuthUser, token: string) => void;
  setLoginUrl: (loginUrl: string) => void;
  logout: () => void;
}

interface StoredAuthUser {
  id?: number | string;
  actor_type?: string | null;
  agent_name?: string | null;
  assigned_project_id?: number | null;
  username?: string | null;
  email?: string | null;
  display_name?: string | null;
  role?: string | null;
}

interface StoredAuthState {
  token?: string | null;
  user?: StoredAuthUser | null;
}

const readStoredAuthState = (key: string): StoredAuthState | null => {
  if (typeof localStorage === "undefined") {
    return null;
  }

  const raw = localStorage.getItem(key);
  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as { state?: StoredAuthState };
    return parsed.state ?? null;
  } catch {
    return null;
  }
};

const normalizeStoredUser = (user: StoredAuthUser | null): AuthUser | null => {
  if (user?.id === undefined || user.id === null || String(user.id) === "") {
    return null;
  }

  return {
    id: String(user.id),
    actor_type: user.actor_type ?? undefined,
    agent_name: user.agent_name ?? undefined,
    assigned_project_id: user.assigned_project_id ?? undefined,
    username: user.username ?? undefined,
    email: user.email ?? undefined,
    display_name: user.display_name ?? undefined,
    role: user.role ?? undefined,
  };
};

const readFrontpageAuth = (): {
  user: AuthUser | null;
  token: string | null;
} => {
  const auth = readStoredAuthState(FRONTPAGE_AUTH_STORAGE_KEY);
  const token =
    typeof auth?.token === "string" && auth.token.trim() !== ""
      ? auth.token
      : null;

  return {
    user: normalizeStoredUser(auth?.user ?? null),
    token,
  };
};

const initialFrontpageAuth = readFrontpageAuth();

const useAuthStore = create<AuthState>()((set) => ({
  user: initialFrontpageAuth.user,
  token: initialFrontpageAuth.token,
  loginUrl: env.webHatcheryLoginUrl,
  setAuth: (user, token) => set({ user, token }),
  setLoginUrl: (loginUrl) => set({ loginUrl }),
  logout: () => set({ user: null, token: null }),
}));

registerAuthTokenResolver(
  () => useAuthStore.getState().token ?? readFrontpageAuth().token,
);

registerUnauthorizedCallback((loginUrl) => {
  useAuthStore.getState().setLoginUrl(loginUrl || env.webHatcheryLoginUrl);
  useAuthStore.getState().logout();
});

export { useAuthStore };
