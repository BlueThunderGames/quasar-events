<template>
  <q-page class="flex flex-center bg-grey-2">
    <q-card flat bordered class="q-pa-lg auth-card">
      <div class="text-h5 q-mb-xs">Create an account</div>
      <div class="text-grey-7 q-mb-lg">Register to reserve tickets for events.</div>

      <q-banner v-if="formMessage" class="bg-red-1 text-negative q-mb-md" rounded>
        {{ formMessage }}
      </q-banner>

      <q-form class="q-gutter-md" @submit.prevent="onSubmit">
        <q-input
          v-model="name"
          label="Name"
          autocomplete="name"
          outlined
          :error="Boolean(errors.name)"
          :error-message="errors.name"
        />

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
          autocomplete="new-password"
          outlined
          hint="At least 8 characters"
          :error="Boolean(errors.password)"
          :error-message="errors.password"
        />

        <q-input
          v-model="passwordConfirmation"
          type="password"
          label="Confirm password"
          autocomplete="new-password"
          outlined
          :error="Boolean(errors.password_confirmation)"
          :error-message="errors.password_confirmation"
        />

        <q-btn
          type="submit"
          color="primary"
          label="Create account"
          no-caps
          class="full-width"
          :loading="submitting"
        />
      </q-form>

      <div class="q-mt-md text-grey-8">
        Already have an account?
        <router-link to="/login">Log in</router-link>
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

const name = ref("");
const email = ref("");
const password = ref("");
const passwordConfirmation = ref("");
const submitting = ref(false);
const formMessage = ref("");
const errors = reactive({
  name: "",
  email: "",
  password: "",
  password_confirmation: ""
});

async function onSubmit() {
  errors.name = "";
  errors.email = "";
  errors.password = "";
  errors.password_confirmation = "";
  formMessage.value = "";
  submitting.value = true;

  try {
    await auth.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value
    });
    await router.push("/");
  } catch (error) {
    errors.name = fieldError(error, "name");
    errors.email = fieldError(error, "email");
    errors.password = fieldError(error, "password");
    errors.password_confirmation = fieldError(error, "password_confirmation");
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
