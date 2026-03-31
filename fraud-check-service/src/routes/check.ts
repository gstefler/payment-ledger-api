import type { FastifyInstance } from "fastify";
import type { TransactionCheckRequest } from "../types.ts";
import { checkFraud } from "../services/fraudCheck.ts";

const UUID_PATTERN = "^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$";

export function registerRoutes(app: FastifyInstance): void {
  app.post<{ Body: TransactionCheckRequest }>(
    "/check",
    {
      schema: {
        body: {
          type: "object",
          required: [
            "transaction_id",
            "merchant_id",
            "type",
            "amount",
            "currency",
            "idempotency_key",
          ],
          properties: {
            transaction_id: { type: "string", pattern: UUID_PATTERN },
            merchant_id: { type: "string", pattern: UUID_PATTERN },
            type: { type: "string", enum: ["payment", "refund"] },
            amount: { type: "number" },
            currency: { type: "string", minLength: 3, maxLength: 3 },
            idempotency_key: { type: "string", minLength: 1 },
          },
          additionalProperties: false,
        },
        response: {
          200: {
            type: "object",
            required: ["approved"],
            properties: {
              approved: { type: "boolean" },
              reason: { type: "string" },
            },
          },
        },
      },
    },
    async (request, reply) => {
      const result = checkFraud(request.body);
      return reply.code(200).send(result);
    },
  );

  app.get("/health", async (_request, reply) => {
    return reply.code(200).send({ status: "ok" });
  });
}
