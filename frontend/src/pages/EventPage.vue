<template>
  <q-page class="q-pa-lg">
    <div class="page">
      <q-btn flat no-caps icon="arrow_back" label="Events" to="/" class="q-mb-md" />

      <div v-if="loading" class="row justify-center q-my-xl">
        <q-spinner-dots color="primary" size="36px" />
      </div>

      <q-banner v-else-if="failed" class="bg-red-1 text-negative" rounded>
        This event could not be loaded.
      </q-banner>

      <template v-else-if="event">
        <div class="text-h4">{{ event.title }}</div>
        <div class="text-primary q-mt-sm">{{ formatEventDate(event.starts_at) }}</div>
        <div class="text-grey-8 q-mt-xs">{{ event.location }}</div>
        <p class="q-mt-md">{{ event.description }}</p>

        <q-banner v-if="reservedMessage" class="bg-green-1 text-positive q-mb-md" rounded>
          {{ reservedMessage }}
        </q-banner>

        <q-tabs v-model="tab" align="left" active-color="primary" indicator-color="primary">
          <q-tab name="tickets" label="Tickets" no-caps />
          <q-tab name="reservations" :label="`Reservations (${event.reservations_count ?? 0})`" no-caps />
        </q-tabs>

        <q-separator />

        <q-tab-panels v-model="tab" animated>
          <q-tab-panel name="tickets" class="q-px-none">
            <q-list v-if="event.tickets?.length" separator bordered>
              <q-item v-for="ticket in event.tickets" :key="ticket.id">
                <q-item-section>
                  <q-item-label>{{ ticket.name }}</q-item-label>
                  <q-item-label caption>{{ ticket.description }}</q-item-label>
                  <div v-if="ticketErrors[ticket.id]" class="text-negative q-mt-xs">
                    {{ ticketErrors[ticket.id] }}
                  </div>
                </q-item-section>
                <q-item-section side top>
                  <div class="text-right">
                    <div>{{ ticket.remaining }} left</div>
                    <div class="text-caption text-grey-7">
                      {{ ticket.reserved }} of {{ ticket.quantity }} reserved
                    </div>
                  </div>
                  <div v-if="auth.user && ticket.remaining > 0" class="row items-center q-gutter-sm q-mt-sm">
                    <q-input
                      v-model.number="quantities[ticket.id]"
                      type="number"
                      dense
                      outlined
                      min="1"
                      :max="ticket.remaining"
                      style="width: 72px"
                      aria-label="Quantity"
                    />
                    <q-btn
                      color="primary"
                      no-caps
                      label="Reserve"
                      :loading="reservingId === ticket.id"
                      @click="reserve(ticket)"
                    />
                  </div>
                  <div v-else-if="!auth.user" class="q-mt-sm">
                    <router-link to="/login">Log in to reserve</router-link>
                  </div>
                  <div v-else class="text-grey-7 q-mt-sm">Sold out</div>
                </q-item-section>
              </q-item>
            </q-list>
            <div v-else class="text-grey-7">No tickets for this event.</div>
          </q-tab-panel>

          <q-tab-panel name="reservations" class="q-px-none">
            <q-banner v-if="reservationsFailed" class="bg-red-1 text-negative q-mb-md" rounded>
              Reservations could not be loaded.
              <template #action>
                <q-btn flat no-caps label="Try again" @click="loadReservations" />
              </template>
            </q-banner>

            <div v-if="reservationsLoading && reservations.length === 0" class="row justify-center q-my-md">
              <q-spinner-dots color="primary" size="32px" />
            </div>

            <template v-else>
              <q-list v-if="reservations.length" separator bordered>
                <q-item v-for="reservation in reservations" :key="reservation.id">
                  <q-item-section>
                    <q-item-label>{{ reservation.user?.name ?? "Guest" }}</q-item-label>
                    <q-item-label caption>{{ reservation.ticket?.name }}</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    {{ reservation.quantity }}
                  </q-item-section>
                </q-item>
              </q-list>
              <div v-else class="text-grey-7">No reservations yet.</div>

              <div v-if="reservationsHasMore && reservations.length > 0" class="row justify-center q-mt-md">
                <q-btn
                  outline
                  color="primary"
                  no-caps
                  label="Load more"
                  :loading="reservationsLoading"
                  @click="loadReservations"
                />
              </div>
            </template>
          </q-tab-panel>
        </q-tab-panels>
      </template>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import axios from "axios";
import { onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { api } from "@/boot/axios";
import { useAuthStore } from "@/stores/auth";
import type { EventDetail, Reservation, ReservationPage, Ticket } from "@/types/event";
import { fieldError, formError } from "@/utils/api-error";
import { formatEventDate } from "@/utils/dates";

const route = useRoute();
const auth = useAuthStore();
const event = ref<EventDetail | null>(null);
const loading = ref(true);
const failed = ref(false);
const tab = ref("tickets");
const quantities = ref<Record<number, number>>({});
const idempotencyKeys = ref<Record<number, string>>({});
const ticketErrors = ref<Record<number, string>>({});
const reservingId = ref<number | null>(null);
const reservedMessage = ref("");
const reservations = ref<Reservation[]>([]);
const reservationPage = ref(1);
const reservationsLoading = ref(false);
const reservationsFailed = ref(false);
const reservationsHasMore = ref(true);

onMounted(async () => {
  try {
    await loadEvent();
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
});

async function loadEvent() {
  const { data } = await api.get<{ data: EventDetail }>(`events/${route.params.id}`);
  event.value = data.data;

  for (const ticket of data.data.tickets ?? []) {
    if (quantities.value[ticket.id] === undefined) {
      quantities.value[ticket.id] = 1;
    }
  }
}

async function reserve(ticket: Ticket) {
  reservingId.value = ticket.id;
  ticketErrors.value[ticket.id] = "";
  reservedMessage.value = "";
  const key = idempotencyKeys.value[ticket.id] ?? crypto.randomUUID();
  idempotencyKeys.value[ticket.id] = key;
  const quantity = Math.max(1, Number(quantities.value[ticket.id] || 1));

  try {
    await api.post(
      "reservations",
      { ticket_id: ticket.id, quantity },
      { headers: { "Idempotency-Key": key } }
    );
    delete idempotencyKeys.value[ticket.id];
    reservedMessage.value = `Reserved ${quantity} ${ticket.name}.`;
    reservations.value = [];
    reservationPage.value = 1;
    reservationsHasMore.value = true;
    await loadEvent();

    if (tab.value === "reservations") {
      await loadReservations();
    }
  } catch (error) {
    if (axios.isAxiosError(error) && error.response) {
      delete idempotencyKeys.value[ticket.id];
    }

    ticketErrors.value[ticket.id] =
      fieldError(error, "quantity") ||
      fieldError(error, "idempotency_key") ||
      formError(error) ||
      "Could not reserve those tickets.";
  } finally {
    reservingId.value = null;
  }
}

watch(tab, value => {
  if (value === "reservations" && reservations.value.length === 0 && reservationsHasMore.value) {
    void loadReservations();
  }
});

async function loadReservations() {
  if (reservationsLoading.value || !reservationsHasMore.value) {
    return;
  }

  reservationsLoading.value = true;
  reservationsFailed.value = false;

  try {
    const { data } = await api.get<ReservationPage>(`events/${route.params.id}/reservations`, {
      params: { page: reservationPage.value }
    });

    reservations.value.push(...data.data);
    reservationsHasMore.value = data.meta.current_page < data.meta.last_page;
    reservationPage.value = data.meta.current_page + 1;
  } catch {
    reservationsFailed.value = true;
  } finally {
    reservationsLoading.value = false;
  }
}
</script>

<style scoped>
.page {
  max-width: 760px;
  margin: 0 auto;
}
</style>
