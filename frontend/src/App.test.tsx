import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  getAgentDoneCheck,
  getCurrentUser,
  getProjects,
  registerAuthTokenResolver,
  registerUnauthorizedCallback,
} from "./api/client";
import App from "./App";
import { useAuthStore } from "./stores/useAuthStore";

vi.mock("./api/client", () => ({
  addSubtask: vi.fn(),
  createAgentToken: vi.fn(),
  createProject: vi.fn(),
  deleteProject: vi.fn(),
  getAgentDoneCheck: vi.fn(),
  getCurrentUser: vi.fn(),
  getProjects: vi.fn(),
  markProjectComplete: vi.fn(),
  registerAuthTokenResolver: vi.fn(),
  registerUnauthorizedCallback: vi.fn(),
}));

describe("App", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
    vi.mocked(registerAuthTokenResolver).mockClear();
    vi.mocked(registerUnauthorizedCallback).mockClear();
    vi.mocked(getAgentDoneCheck).mockReset();
    vi.mocked(getCurrentUser).mockReset();
    vi.mocked(getProjects).mockReset();
    useAuthStore.getState().logout();
  });

  it("renders application title", async () => {
    render(<App />);
    expect(await screen.findByText("Is It Done Yet?")).toBeInTheDocument();
  });

  it("shows WebHatchery login when no token is available", async () => {
    render(<App />);

    const loginLink = await screen.findByRole("link", {
      name: "Open WebHatchery Login",
    });

    expect(loginLink).toHaveAttribute("href", "https://webhatchery.au/login");
  });

  it("does not overwrite or clear WebHatchery login storage", () => {
    const frontpageAuth = JSON.stringify({
      state: {
        token: "frontpage-token",
        user: {
          id: "6",
          display_name: "WebHatchery User",
        },
      },
    });

    localStorage.setItem("auth-storage", frontpageAuth);

    useAuthStore.getState().setAuth({ id: "isitdoneyet-user" }, "app-token");

    expect(localStorage.getItem("auth-storage")).toBe(frontpageAuth);
    expect(localStorage.getItem("isitdoneyet-auth-storage")).toBeNull();

    useAuthStore.getState().logout();

    expect(localStorage.getItem("auth-storage")).toBe(frontpageAuth);
    expect(localStorage.getItem("isitdoneyet-auth-storage")).toBeNull();
  });

  it("shows agent status for API-created project work", async () => {
    useAuthStore.getState().setAuth({ id: "agent-test-user" }, "test-token");
    vi.mocked(getCurrentUser).mockResolvedValue({
      success: true,
      data: {
        id: "agent-test-user",
        actor_type: "agent",
        agent_name: "Codex",
        assigned_project_id: 28,
        display_name: "Agent Test User",
      },
    });

    vi.mocked(getProjects).mockResolvedValue({
      success: true,
      data: [
        {
          id: 28,
          title: "is ai usage visible to an end user on isitdoneyet",
          description: "Created through the agent API.",
          completed: false,
          parent_id: null,
          created_at: "2026-05-19 10:00:00",
          updated_at: "2026-05-19 10:00:00",
          progress: 66,
          children: [
            {
              id: 31,
              title:
                "Verify the browser shows the agent-created project and status",
              description:
                "Load the UI and confirm the agent project is visible.",
              completed: false,
              parent_id: 28,
              created_at: "2026-05-19 10:00:00",
              updated_at: "2026-05-19 10:00:00",
              progress: 0,
              children: [],
            },
          ],
        },
      ],
    });

    vi.mocked(getAgentDoneCheck).mockResolvedValue({
      success: true,
      data: {
        project_id: 28,
        project_title: "is ai usage visible to an end user on isitdoneyet",
        question: "Is it done yet?",
        answer: "no",
        is_done: false,
        progress: 66,
        counts: {
          total: 4,
          completed: 2,
          open: 2,
        },
        next_action: "work",
        why_not: [],
        next_tasks: [
          {
            id: 31,
            title:
              "Verify the browser shows the agent-created project and status",
            description:
              "Load the UI and confirm the agent project is visible.",
            parent_id: 28,
            completed: false,
            progress: 0,
          },
        ],
        reassessment_questions: [],
      },
    });

    render(<App />);

    expect(
      (
        await screen.findAllByText(
          "is ai usage visible to an end user on isitdoneyet",
        )
      ).length,
    ).toBeGreaterThanOrEqual(3);
    expect(
      await screen.findByText(
        /Signed in as Codex \(agent for Agent Test User\)/,
      ),
    ).toBeInTheDocument();
    expect(await screen.findByText("Agent Status")).toBeInTheDocument();
    expect(await screen.findByText("Work")).toBeInTheDocument();
    expect(
      (
        await screen.findAllByText(
          "Verify the browser shows the agent-created project and status",
        )
      ).length,
    ).toBeGreaterThanOrEqual(3);

    expect(
      await screen.findByLabelText(
        "Project tree for is ai usage visible to an end user on isitdoneyet",
      ),
    ).toBeInTheDocument();
    expect(
      await screen.findAllByRole("button", {
        name: "Hide Tree",
      }),
    ).toHaveLength(1);
    expect(await screen.findByText("Tree View")).toBeInTheDocument();
    expect(await screen.findByText("2 tasks")).toBeInTheDocument();
    expect(await screen.findByText("Blocked - 66%")).toBeInTheDocument();

    const leafTreeButtons = await screen.findAllByRole("button", {
      name: "View Tree",
    });
    expect(leafTreeButtons.length).toBeGreaterThan(0);
  });
});
