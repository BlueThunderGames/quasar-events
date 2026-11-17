import type { RouteRecordRaw } from "vue-router";

declare module "vue-router" {
  interface RouteMeta {
    guest?: boolean;
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    component: () => import("@/layouts/MainLayout.vue"),
    children: [
      { path: "", component: () => import("@/pages/IndexPage.vue") },
      { path: "events/:id", component: () => import("@/pages/EventPage.vue") },
      {
        path: "login",
        component: () => import("@/pages/LoginPage.vue"),
        meta: { guest: true }
      },
      {
        path: "register",
        component: () => import("@/pages/RegisterPage.vue"),
        meta: { guest: true }
      }
    ]
  },

  {
    path: "/:catchAll(.*)*",
    component: () => import("@/pages/ErrorNotFound.vue")
  }
];

export default routes;
