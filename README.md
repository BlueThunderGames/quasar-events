# Quasar Events

Quasar Events is a Laravel API with a Quasar frontend. Visitors browse events and the tickets offered for each one. A signed-in user reserves a quantity of a ticket to RSVP.

## Accounts

Registration and login issue a Sanctum bearer token. A new login revokes that user's older tokens. Logout revokes the token used on that request. Login and registration are limited to five attempts per minute. Reserving a ticket, listing your own reservations, and canceling one require the token.

Send it as `Authorization: Bearer <token>`.

## Events and tickets

Events are public. The list is ordered by start time and paginated. An event's detail includes its ticket types and how many places remain. A ticket's quantity is its capacity. Remaining places are that capacity minus the quantities already reserved.

The people who reserved are a separate list on the event. That list shows a name, not an email address.

## Reservations

A reservation is one ticket type and a quantity, placed by the signed-in user. The same person may reserve the same ticket more than once, as long as the remaining capacity allows it. A request that would exceed the remaining quantity is rejected. A ticket cannot be reserved after the event has ended. Only the owner can cancel, and canceling returns those places to the ticket.

Creating a reservation requires an `Idempotency-Key` header (a UUID). Repeating the same key with the same ticket and quantity returns the original reservation and does not take stock again. The same key with a different ticket or quantity is rejected.

A new reservation queues a confirmation email. Repeating the same key queues that email only if it never made it onto the queue. The local mailer writes the message to the log, so a queue worker has to be running.

## Logging

API requests accept a W3C `traceparent` header and return one. Each request is a trace. Register, login, logout, creating a reservation, canceling one, and sending the confirmation are spans on that trace. The confirmation job keeps the trace id after it leaves the request. Log lines use those ids. They do not include passwords, tokens, or email addresses.

## Layout

- `api` holds the Laravel application: models, migrations, authentication, and the JSON API.
- `frontend` holds the Quasar application: registration, login, the event list, the event page, and the signed-in user's reservations.

## Local setup

Create the API environment from the example file. The example has empty secrets. The real `.env` stays on your machine. The default database is SQLite.

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In another terminal, from `api`:

```bash
php artisan queue:work
```

The seeded account is `test@example.com` / `password`.

The frontend reads `QCLI_API_URL` from `frontend/.env` (see `.env.example`). It expects the API at `http://localhost:8000/api` and runs on port 9000.

```bash
cd frontend
pnpm install
pnpm dev
```

API tests:

```bash
cd api
php artisan test
```
