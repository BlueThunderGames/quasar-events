export interface EventSummary {
  id: number;
  title: string;
  slug: string;
  description: string;
  location: string;
  starts_at: string;
  ends_at: string | null;
}

export interface Ticket {
  id: number;
  name: string;
  description: string;
  quantity: number;
  reserved: number;
  remaining: number;
}

export interface Reservation {
  id: number;
  quantity: number;
  created_at: string;
  user?: {
    id: number;
    name: string;
  };
  ticket?: {
    id: number;
    name: string;
    event?: {
      id: number;
      title: string;
    };
  };
}

export interface EventDetail extends EventSummary {
  tickets?: Ticket[];
  reservations_count?: number;
}

export interface PageMeta {
  current_page: number;
  last_page: number;
  total: number;
}

export interface EventPage {
  data: EventSummary[];
  meta: PageMeta;
}

export interface ReservationPage {
  data: Reservation[];
  meta: PageMeta;
}
