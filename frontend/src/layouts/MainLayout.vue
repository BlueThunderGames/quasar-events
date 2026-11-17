<template>
  <q-layout view="hHh lpR fFf">
    <q-header elevated>
      <q-toolbar>
        <q-toolbar-title>
          <router-link to="/" class="text-white" style="text-decoration: none">
            Events
          </router-link>
        </q-toolbar-title>

        <div v-if="auth.user" class="row items-center q-gutter-sm">
          <span>{{ auth.user.name }}</span>
          <q-btn flat no-caps label="My reservations" to="/reservations" />
          <q-btn flat no-caps label="Log out" :loading="loggingOut" @click="onLogout" />
        </div>
        <div v-else class="row items-center q-gutter-sm">
          <q-btn flat no-caps label="Log in" to="/login" />
          <q-btn unelevated no-caps color="white" text-color="primary" label="Register" to="/register" />
        </div>
      </q-toolbar>
    </q-header>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const router = useRouter();
const loggingOut = ref(false);

async function onLogout() {
  loggingOut.value = true;

  try {
    await auth.logout();
    await router.push("/");
  } finally {
    loggingOut.value = false;
  }
}
</script>
