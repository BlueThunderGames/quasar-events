<template>
  <q-page class="q-pa-lg">
    <div class="page">
      <div class="text-h4 q-mb-xs">Events</div>
      <div class="text-body1 text-grey-8 q-mb-lg">
        Browse upcoming events. Log in when you want to reserve a ticket.
      </div>

      <q-banner v-if="failed" class="bg-red-1 text-negative q-mb-md" rounded>
        Could not load events.
        <template #action>
          <q-btn flat no-caps label="Try again" @click="loadMore" />
        </template>
      </q-banner>

      <div v-if="loading && events.length === 0" class="row justify-center q-my-xl">
        <q-spinner-dots color="primary" size="36px" />
      </div>

      <div v-else-if="loaded && events.length === 0 && !failed" class="text-grey-7">
        No events yet.
      </div>

      <div class="column q-gutter-md">
        <router-link
          v-for="event in events"
          :key="event.id"
          :to="`/events/${event.id}`"
          class="event-link"
        >
          <q-card flat bordered>
            <q-card-section>
              <div class="text-h6">{{ event.title }}</div>
              <div class="text-primary q-mt-xs">
                {{ formatEventDate(event.starts_at) }}
              </div>
              <div class="text-grey-8 q-mt-xs">{{ event.location }}</div>
              <div class="q-mt-sm ellipsis-2-lines">{{ event.description }}</div>
            </q-card-section>
          </q-card>
        </router-link>
      </div>

      <div v-if="hasMore && events.length > 0" class="row justify-center q-mt-lg">
        <q-btn
          outline
          color="primary"
          no-caps
          label="Load more"
          :loading="loading"
          @click="loadMore"
        />
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { api } from "@/boot/axios";
import type { EventPage, EventSummary } from "@/types/event";
import { formatEventDate } from "@/utils/dates";

const events = ref<EventSummary[]>([]);
const page = ref(1);
const loading = ref(false);
const loaded = ref(false);
const failed = ref(false);
const hasMore = ref(true);

async function loadMore() {
  if (loading.value || !hasMore.value) {
    return;
  }

  loading.value = true;
  failed.value = false;

  try {
    const { data } = await api.get<EventPage>("events", {
      params: { page: page.value }
    });

    events.value.push(...data.data);
    hasMore.value = data.meta.current_page < data.meta.last_page;
    page.value = data.meta.current_page + 1;
    loaded.value = true;
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  void loadMore();
});
</script>

<style scoped>
.page {
  max-width: 760px;
  margin: 0 auto;
}

.event-link {
  color: inherit;
  text-decoration: none;
}
</style>
