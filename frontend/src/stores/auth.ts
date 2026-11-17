import { api } from "@/boot/axios";
import { defineStore, acceptHMRUpdate } from "pinia";

export interface User {
  id: number;
  name: string;
  email: string;
}

interface UserResponse {
  data: User;
}

interface AuthResponse extends UserResponse {
  token: string;
}

interface Credentials {
  email: string;
  password: string;
}

interface Registration extends Credentials {
  name: string;
  password_confirmation: string;
}

export const useAuthStore = defineStore("auth", {
  state: () => ({
    token: localStorage.getItem("token"),
    user: null as User | null,
    ready: false
  }),

  getters: {
    isAuthenticated: state => Boolean(state.token)
  },

  actions: {
    setSession(token: string, user: User) {
      this.token = token;
      this.user = user;
      localStorage.setItem("token", token);
    },

    clear() {
      this.token = null;
      this.user = null;
      localStorage.removeItem("token");
    },

    async fetchUser() {
      if (!this.token) {
        this.ready = true;
        return;
      }

      try {
        const { data } = await api.get<UserResponse>("user");
        this.user = data.data;
      } catch {
        this.clear();
      } finally {
        this.ready = true;
      }
    },

    async register(payload: Registration) {
      const { data } = await api.post<AuthResponse>("register", payload);
      this.setSession(data.token, data.data);
    },

    async login(payload: Credentials) {
      const { data } = await api.post<AuthResponse>("login", payload);
      this.setSession(data.token, data.data);
    },

    async logout() {
      try {
        await api.post("logout");
      } finally {
        this.clear();
      }
    }
  }
});

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot));
}
