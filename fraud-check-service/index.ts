import Fastify from "fastify";
import { registerRoutes } from "./src/routes/check.ts";

const PORT = parseInt(Bun.env["PORT"] ?? "3000", 10);
const HOST = Bun.env["HOST"] ?? "0.0.0.0";

const app = Fastify({ logger: true });

registerRoutes(app);

await app.listen({ port: PORT, host: HOST });
