<template>
  <q-page class="flex flex-center bg-grey-2">
    <q-card flat bordered class="q-pa-lg auth-card">
      <div class="text-h5 q-mb-xs">Log in</div>
      <div class="text-grey-7 q-mb-lg">Use your account to reserve tickets.</div>

      <q-banner v-if="formMessage" class="bg-red-1 text-negative q-mb-md" rounded>
        {{ formMessage }}
      </q-banner>

      <q-form class="q-gutter-md" @submit.prevent="onSubmit">
        <q-input
          v-model="email"
          type="email"
          label="Email"
          autocomplete="email"
          outlined
          :error="Boolean(errors.email)"
          :error-message="errors.email"
        />

        <q-input
          v-model="password"
          type="password"
          label="Password"
          autocomplete="current-password"
          outlined
          :error="Boolean(errors.password)"
          :error-message="errors.password"
        />

        <q-btn
          type="submit"
          color="primary"
          label="Log in"
          no-caps
          class="full-width"
          :loading="submitting"
        />
      </q-form>

      <div class="q-mt-md text-grey-8">
        Need an account?
        <router-link to="/register">Register</router-link>
      </div>
    </q-card>
  </q-page>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { fieldError, formError } from "@/utils/api-error";

const router = useRouter();
const auth = useAuthStore();

const email = ref("");
const password = ref("");
const submitting = ref(false);
const formMessage = ref("");
const errors = reactive({
  email: "",
  password: ""
});

async function onSubmit() {
  errors.email = "";
  errors.password = "";
  formMessage.value = "";
  submitting.value = true;

  try {
    await auth.login({
      email: email.value,
      password: password.value
    });
    await router.push("/");
  } catch (error) {
    errors.email = fieldError(error, "email");
    errors.password = fieldError(error, "password");
    formMessage.value = formError(error);
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.auth-card {
  width: 420px;
  max-width: 92vw;
}
</style>
