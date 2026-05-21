const apiBaseUrl = import.meta.env.VITE_API_BASE_URL;
const webHatcheryLoginUrl = import.meta.env.VITE_WEBHATCHERY_LOGIN_URL;

if (!apiBaseUrl) {
  throw new Error("VITE_API_BASE_URL environment variable is required.");
}

if (!webHatcheryLoginUrl) {
  throw new Error(
    "VITE_WEBHATCHERY_LOGIN_URL environment variable is required.",
  );
}

export const env = {
  apiBaseUrl: apiBaseUrl.replace(/\/+$/, ""),
  webHatcheryLoginUrl,
};
