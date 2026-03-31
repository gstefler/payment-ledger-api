# Development

Start the full dev stack (hot-reload enabled for both services):

```bash
docker compose -f compose.dev.yml up -d --build
```

| Service | URL |
|---|---|
| Laravel API | http://localhost:8000 |
| Fraud-check service | http://localhost:3000 |
| PostgreSQL | localhost:5432 |

Environment is loaded from `.env.dev` (root). The `payment-service/.env` is ignored for any key already defined there.

Migrations run automatically on `payment-service` startup. Queue workers (`fraud-check`, `webhook`) run inside the `payment-service` container via supervisord using the `database` driver.

Hot reload:
- **PHP** — files are volume-mounted; changes are picked up on the next request.
- **Bun** — runs with `--hot`; modules reload without restarting the process.

# The task
You are a fintech company that provides payment services to merchants. Build a
simplified transaction management system consisting of two components:

- Laravel API (payment-service) — the main application. Manage merchants, record and query transactions,
and track balances.
- TypeScript microservice (fraud-check-service) — a separate service that validates transactions before
they are finalized. It can be considered a simple fraud-check simulation.

# Business requirements
1. **Merchant Management**: It should be possible to create traders and query their data (including their balances).
2. **Transaction Recording**: Transactions can be recorded. There are two types: payment and refund. A payment increases the merchant's balance, while a refund decreases it. A negative balance must not
occur.
3. **Idempotence**: The same transaction must not be executable multiple times
4. **Fraud Check**: Before finalizing a transaction, it must be validated by the fraud-check service. The fraud-check service should have a simple logic to determine if a transaction is fraudulent (e.g., transactions above a certain amount are flagged as fraudulent).
5. **Transaction Querying**: It should be possible to query transactions by merchant and by date range.

# Technical requirements
1. **Laravel API**:
    - Use Laravel to build the API.
    - PostgreSQL should be used as the database.
    - Use Eloquent ORM and Laravel migrations for database management.
    - Implement RESTful endpoints for managing merchants and transactions.
2. **TypeScript Microservice**:
    - Use Bun.js to build the microservice.
    - Use Fastify for the HTTP server.
    - At least a few transaction validation rules (based on the amount, curency, etc.) should be implemented.

# Testing
- Use Pest for testing the Laravel API.

# Design and architecture
- The Laravel API should use Laravel's queue system (with a Redis queue) to handle transaction processing asynchronously. When a transaction is created, it should be dispatched to a queue, where it will be processed and validated by the fraud-check microservice before being finalized.
- Each transaction request is returned a HTTP 202 Accepted response immediately. The final status of the transaction (approved or rejected) can be queried later through a separate endpoint. Or a webhook url can be configured to receive the final status of the transaction once it is processed.
- Create an append only ledger for transactions. Each transaction has an unique identifier, a foreign key reference to the merchant, an enum type (payment or refund), an amount, a timestamp, and a status (pending, approved, rejected) etc. The ledger should be immutable, meaning that once a transaction is recorded, it cannot be modified or deleted.
- Require an idempotency key for each transaction request to ensure that the same transaction is not processed multiple times.
- On all models use UUIDs as primary keys instead of auto-incrementing integers.
- Refunds requests should reference the original payment transaction they are refunding.
- The balance should be redundantly stored in the merchant's record for quick access. Each time a transaction is approved, the balance should be updated accordingly. Ensure that race conditions are handled properly.
- Use single store endpoint for creating the transactions. The fraud-check should know the type of transaction too.
- Authentication and authorization are not required, remove the user model and any related authentication scaffolding from the Laravel API.
- Use separate Laravel queues for the fraud-check and webhook processing
- Make sure that the fraud check is retried a few times with exponential backoff, before failing the transaction, same for the webhook