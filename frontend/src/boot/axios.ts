import { defineBoot } from "#q-app";
import { useAuthStore } from "@/stores/auth";
import axios from "axios";

const api = axios.create({
  baseURL: import.meta.env.QCLI_API_URL || "http://localhost:8000/api",
  headers: {
    Accept: "application/json"
  }
});

api.interceptors.request.use(config => {
  const token = localStorage.getItem("token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

export default defineBoot(({ store, router }) => {
  api.interceptors.response.use(
    response => response,
    error => {
      if (axios.isAxiosError(error) && error.response?.status === 401) {
        useAuthStore(store).clear();

        if (router.currentRoute.value.meta.requiresAuth) {
          void router.push("/login");
        }
      }

      return Promise.reject(error);
    }
  );
});

export { api };
