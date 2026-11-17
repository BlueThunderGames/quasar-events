<template>
  <q-page class="q-pa-lg">
    <div class="page">
      <div class="text-h4 q-mb-lg">My reservations</div>

      <q-banner v-if="failed" class="bg-red-1 text-negative q-mb-md" rounded>
        Reservations could not be loaded.
        <template #action>
          <q-btn flat no-caps label="Try again" @click="load" />
        </template>
      </q-banner>

      <div v-if="loading && reservations.length === 0" class="row justify-center q-my-xl">
        <q-spinner-dots color="primary" size="36px" />
      </div>

      <div v-else-if="loaded && reservations.length === 0 && !failed" class="text-grey-7">
        You have no reservations yet.
      </div>

      <q-list v-if="reservations.length" separator bordered>
        <q-item v-for="reservation in reservations" :key="reservation.id">
          <q-item-section>
            <q-item-label>
              <router-link
                v-if="reservation.ticket?.event"
                :to="`/events/${reservation.ticket.event.id}`"
              >
                {{ reservation.ticket.event.title }}
              </router-link>
            </q-item-label>
            <q-item-label caption>
              {{ reservation.ticket?.name }} · {{ reservation.quantity }}
            </q-item-label>
          </q-item-section>
          <q-item-section side>
            <q-btn
              flat
              no-caps
              color="negative"
              label="Cancel"
              :loading="cancellingId === reservation.id"
              @click="cancel(reservation)"
            />
          </q-item-section>
        </q-item>
      </q-list>

      <div v-if="hasMore && reservations.length > 0" class="row justify-center q-mt-lg">
        <q-btn outline color="primary" no-caps label="Load more" :loading="loading" @click="load" />
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { api } from "@/boot/axios";
import type { Reservation, ReservationPage } from "@/types/event";

const reservations = ref<Reservation[]>([]);
const page = ref(1);
const loading = ref(false);
const loaded = ref(false);
const failed = ref(false);
const hasMore = ref(true);
const cancellingId = ref<number | null>(null);

onMounted(() => {
  void load();
});

async function load() {
  if (loading.value || !hasMore.value) {
    return;
  }

  loading.value = true;
  failed.value = false;

  try {
    const { data } = await api.get<ReservationPage>("me/reservations", {
      params: { page: page.value }
    });

    reservations.value.push(...data.data);
    hasMore.value = data.meta.current_page < data.meta.last_page;
    page.value = data.meta.current_page + 1;
    loaded.value = true;
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
}

async function cancel(reservation: Reservation) {
  cancellingId.value = reservation.id;

  try {
    await api.delete(`reservations/${reservation.id}`);
    reservations.value = reservations.value.filter(item => item.id !== reservation.id);
  } catch {
    failed.value = true;
  } finally {
    cancellingId.value = null;
  }
}
</script>

<style scoped>
.page {
  max-width: 760px;
  margin: 0 auto;
}
</style>
