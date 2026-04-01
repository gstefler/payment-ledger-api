# Payment Ledger API

A simplified transaction management system for payment services. Merchants can be registered, payments and refunds recorded asynchronously, and balances tracked per currency.

## Services

| Service | Dev port | Prod port | Description |
|---|---|---|---|
| Laravel API | 8000 | 80 | Main payment API |
| Fraud-check service | 3000 | 3000 | Transaction validation microservice |
| Webhook receiver | 4000 | 4000 | Debug helper — prints incoming webhooks as JSON |
| PostgreSQL | 5432 | — | Database (not exposed in prod) |

---

## Development

### Start

```bash
docker compose -f compose.dev.yml up -d --build
```

Environment is loaded from `.env.dev`. Migrations run automatically on startup. The queue worker runs inside the `payment-service` container via supervisord, processing both `fraud-check` and `webhook` queues.

Hot reload is enabled: PHP files are volume-mounted and picked up on the next request.

### API & docs

- API base: `http://localhost:8000/api`
- Interactive API docs (Scramble/OpenAPI): `http://localhost:8000/docs/api`

### Watching webhook deliveries

Set a merchant's `webhook_url` to `http://webhook-receiver:4000/anything` and tail the container:

```bash
docker compose -f compose.dev.yml logs -f webhook-receiver
```

Payloads are printed as formatted JSON with a UTC timestamp.

### Running tests

```bash
cd payment-service
php artisan test --compact
```

---

## Production

### Start

```bash
docker compose up -d --build
```

Environment is loaded from `.env.production`. The stack runs nginx + php-fpm + a dedicated queue worker container. Migrations run automatically on php-fpm startup.

### Required environment variables

Copy `.env.production` and set at minimum:

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`) |
| `DB_PASSWORD` | PostgreSQL password |
| `FRAUD_CHECK_SERVICE_URL` | URL of the fraud-check service (default: `http://fraud-check-service:3000`) |

### API & docs

- API base: `http://your-host/api`
- Interactive API docs: `http://your-host/docs/api`

---

## Features

### Merchant management

- Create merchants with a name, email, and an optional webhook URL
- List all merchants or retrieve a single merchant by ID
- Each merchant tracks balances independently per currency — a USD payment and an AUD payment are stored separately and never mixed

### Transaction recording

- Two transaction types: **payment** (credits the merchant's balance) and **refund** (debits it)
- Refunds must reference an original approved payment transaction belonging to the same merchant
- A refund is rejected if the merchant has insufficient balance in the relevant currency
- Transactions are immutable once created — status can only transition from `pending` to `approved` or `rejected`

### Idempotency

- Every transaction request requires a client-supplied `idempotency_key`
- Submitting the same key twice returns the existing transaction (HTTP 200) instead of creating a duplicate, even under concurrent requests

### Asynchronous fraud check

- Transaction creation returns HTTP 202 immediately with a `pending` status
- A queued job calls the fraud-check microservice before finalising the transaction
- The fraud-check service applies these rules (in order):
  - Amount must be greater than zero
  - Payments below 0.01 are rejected as potential card-probing
  - Payments above 10,000 are rejected (configurable via `MAX_PAYMENT_AMOUNT`)
  - Refunds above 5,000 are rejected (configurable via `MAX_REFUND_AMOUNT`)
  - Currency must be one of: USD, EUR, GBP, JPY, CAD, AUD, CHF (configurable via `ALLOWED_CURRENCIES`)
- If the fraud-check service is unavailable, the job retries up to 5 times with exponential backoff (10 s, 30 s, 60 s, 120 s, 300 s). After all retries are exhausted the transaction is rejected with reason `"Fraud check service unavailable"`

### Webhooks

- Merchants with a `webhook_url` receive a POST request after every transaction is finalised (approved or rejected)
- Webhook delivery is queued separately and retried up to 5 times with the same exponential backoff policy
- Permanent delivery failures are logged without affecting the transaction status

### Transaction querying

- List transactions with optional filters:
  - `merchant_id` — filter by merchant
  - `from` — transactions created on or after this date
  - `to` — transactions created on or before this date
- Retrieve a single transaction by ID to poll for its final status

---

## API reference

Full interactive documentation is available at `/docs/api` (OpenAPI/Swagger UI, powered by Scramble).

### Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/merchants` | List all merchants |
| `POST` | `/api/merchants` | Create a merchant |
| `GET` | `/api/merchants/{id}` | Get a merchant with per-currency balances |
| `GET` | `/api/transactions` | List transactions (filterable) |
| `POST` | `/api/transactions` | Submit a transaction |
| `GET` | `/api/transactions/{id}` | Get transaction status |

### Transaction lifecycle

```
POST /api/transactions  →  202 Accepted (status: pending)
        ↓ queued
  fraud-check service
        ↓
  approved / rejected  →  webhook delivery (if configured)
        ↓
GET /api/transactions/{id}  →  200 OK (status: approved | rejected)
```
