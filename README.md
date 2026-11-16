# Quasar Events

Quasar Events is a Laravel API with a Quasar frontend. Visitors browse events and the tickets offered for each one. A signed-in user reserves a quantity of a ticket to RSVP.

## Accounts

Registration and login issue a Sanctum bearer token. Logout revokes the token used on that request. Reserving a ticket requires that token.

## Events and tickets

Events are public. Each event lists its ticket types and how many places remain. A ticket's quantity is its capacity. Remaining places are that capacity minus the quantities already reserved.

## Reservations

A reservation is one ticket type and a quantity, placed by the signed-in user. The same person may reserve the same ticket more than once, as long as the remaining capacity allows it. A request that would exceed the remaining quantity is rejected. Canceling a reservation returns those places to the event.

## Layout

- `api` holds the Laravel application: models, migrations, authentication, and the JSON API.
- `frontend` holds the Quasar application: registration, login, the event list, and the event page with its tickets and reservations.

## Local setup

Create the API environment from the example file. The example has empty secrets. The real `.env` stays on your machine.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

```bash
cd frontend
npm install
npm run dev
```

The API listens on `http://127.0.0.1:8000`. Send the bearer token on authenticated requests as `Authorization: Bearer <token>`.
