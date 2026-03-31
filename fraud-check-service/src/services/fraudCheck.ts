import type { FraudCheckResult, TransactionCheckRequest } from "../types.ts";

const MAX_PAYMENT_AMOUNT = parseFloat(Bun.env["MAX_PAYMENT_AMOUNT"] ?? "10000");
const MAX_REFUND_AMOUNT = parseFloat(Bun.env["MAX_REFUND_AMOUNT"] ?? "5000");
const ALLOWED_CURRENCIES = new Set(
  (Bun.env["ALLOWED_CURRENCIES"] ?? "USD,EUR,GBP,JPY,CAD,AUD,CHF").split(","),
);

const MICRO_TRANSACTION_THRESHOLD = 0.01;

function reject(reason: string): FraudCheckResult {
  return { approved: false, reason };
}

function checkNonPositiveAmount(tx: TransactionCheckRequest): FraudCheckResult | null {
  if (tx.amount <= 0) {
    return reject(`Amount must be greater than zero, got ${tx.amount}`);
  }
  return null;
}

function checkMicroTransaction(tx: TransactionCheckRequest): FraudCheckResult | null {
  if (tx.type === "payment" && tx.amount < MICRO_TRANSACTION_THRESHOLD) {
    return reject(
      `Payment amount ${tx.amount} is below the minimum threshold of ${MICRO_TRANSACTION_THRESHOLD} — possible card-probing attempt`,
    );
  }
  return null;
}

function checkHighValuePayment(tx: TransactionCheckRequest): FraudCheckResult | null {
  if (tx.type === "payment" && tx.amount > MAX_PAYMENT_AMOUNT) {
    return reject(
      `Payment amount ${tx.amount} exceeds the maximum allowed amount of ${MAX_PAYMENT_AMOUNT}`,
    );
  }
  return null;
}

function checkHighValueRefund(tx: TransactionCheckRequest): FraudCheckResult | null {
  if (tx.type === "refund" && tx.amount > MAX_REFUND_AMOUNT) {
    return reject(
      `Refund amount ${tx.amount} exceeds the maximum allowed amount of ${MAX_REFUND_AMOUNT}`,
    );
  }
  return null;
}

function checkUnsupportedCurrency(tx: TransactionCheckRequest): FraudCheckResult | null {
  if (!ALLOWED_CURRENCIES.has(tx.currency.toUpperCase())) {
    return reject(
      `Currency ${tx.currency} is not supported. Allowed currencies: ${[...ALLOWED_CURRENCIES].join(", ")}`,
    );
  }
  return null;
}

const rules = [
  checkNonPositiveAmount,
  checkMicroTransaction,
  checkHighValuePayment,
  checkHighValueRefund,
  checkUnsupportedCurrency,
];

export function checkFraud(tx: TransactionCheckRequest): FraudCheckResult {
  for (const rule of rules) {
    const result = rule(tx);
    if (result !== null) return result;
  }
  return { approved: true };
}
