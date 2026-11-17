import axios from "axios";

interface ErrorBody {
  errors?: Record<string, string[]>;
}

export function fieldError(error: unknown, field: string): string {
  if (!axios.isAxiosError<ErrorBody>(error)) {
    return "";
  }

  const messages = error.response?.data?.errors?.[field];

  return Array.isArray(messages) ? (messages[0] ?? "") : "";
}

export function formError(error: unknown): string {
  if (!axios.isAxiosError(error)) {
    return "Something went wrong. Try again.";
  }

  if (!error.response) {
    return "The API is not reachable.";
  }

  return "";
}
