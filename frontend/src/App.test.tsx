import { render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";

describe("App", () => {
  beforeEach(() => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue({
      ok: true,
      json: async () => ({
        success: true,
        data: [],
      }),
    } as Response);
  });

  it("renders application title", async () => {
    render(<App />);
    expect(await screen.findByText("Is It Done Yet?")).toBeInTheDocument();
  });
});
