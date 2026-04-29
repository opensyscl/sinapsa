import axios from "axios";

const baseURL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:48000";

export const api = axios.create({
  baseURL,
  withCredentials: false,
  headers: { "Content-Type": "application/json", Accept: "application/json" },
});

api.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    try {
      const raw = localStorage.getItem("sinapsa-auth");
      if (raw) {
        const token = JSON.parse(raw)?.state?.token;
        if (token) config.headers.Authorization = `Bearer ${token}`;
      }
    } catch {
      // noop
    }
  }
  return config;
});

api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err?.response?.status === 401 && typeof window !== "undefined") {
      localStorage.removeItem("sinapsa-auth");
    }
    return Promise.reject(err);
  },
);
