import "@testing-library/jest-dom/vitest";
import { vi } from "vitest";

vi.stubEnv("VITE_API_BASE_URL", "http://127.0.0.1/isitdoneyet/api/v1");
vi.stubEnv("VITE_WEBHATCHERY_LOGIN_URL", "https://webhatchery.au/login");
