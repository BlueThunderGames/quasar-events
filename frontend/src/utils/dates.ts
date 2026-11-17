import { date } from "quasar";

export function formatEventDate(value: string | null): string {
  if (!value) {
    return "";
  }

  return date.formatDate(value, "ddd, MMM D, YYYY · h:mm A");
}
