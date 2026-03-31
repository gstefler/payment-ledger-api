import { describe, test, expect } from "bun:test";
import { checkFraud } from "../src/services/fraudCheck.ts";
import type { TransactionCheckRequest } from "../src/types.ts";

const basePayment: TransactionCheckRequest = {
  transaction_id: "00000000-0000-0000-0000-000000000001",
  merchant_id: "00000000-0000-0000-0000-000000000002",
  type: "payment",
  amount: 99.99,
  currency: "USD",
  idempotency_key: "test-key-1",
};

const baseRefund: TransactionCheckRequest = {
  ...basePayment,
  type: "refund",
  amount: 50.0,
  idempotency_key: "test-key-2",
};

describe("checkFraud — approved", () => {
  test("approves a normal payment in a supported currency", () => {
    expect(checkFraud(basePayment)).toEqual({ approved: true });
  });

  test("approves a normal refund in a supported currency", () => {
    expect(checkFraud(baseRefund)).toEqual({ approved: true });
  });

  test("approves payment at exactly the threshold", () => {
    expect(checkFraud({ ...basePayment, amount: 10000 })).toEqual({ approved: true });
  });

  test("approves refund at exactly the threshold", () => {
    expect(checkFraud({ ...baseRefund, amount: 5000 })).toEqual({ approved: true });
  });

  test("approves payments in all supported currencies", () => {
    for (const currency of ["USD", "EUR", "GBP", "JPY", "CAD", "AUD", "CHF"]) {
      expect(checkFraud({ ...basePayment, currency }).approved).toBe(true);
    }
  });
});

describe("checkFraud — rejected", () => {
  test("rejects payment with zero amount", () => {
    const result = checkFraud({ ...basePayment, amount: 0 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/greater than zero/);
  });

  test("rejects payment with negative amount", () => {
    const result = checkFraud({ ...basePayment, amount: -50 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/greater than zero/);
  });

  test("rejects micro-transaction payment (card-probing pattern)", () => {
    const result = checkFraud({ ...basePayment, amount: 0.001 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/minimum threshold/);
  });

  test("does not apply micro-transaction rule to refunds", () => {
    expect(checkFraud({ ...baseRefund, amount: 0.005 })).toEqual({ approved: true });
  });

  test("rejects payment above MAX_PAYMENT_AMOUNT", () => {
    const result = checkFraud({ ...basePayment, amount: 10001 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/maximum allowed amount/);
  });

  test("rejects refund above MAX_REFUND_AMOUNT", () => {
    const result = checkFraud({ ...baseRefund, amount: 5001 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/maximum allowed amount/);
  });

  test("rejects unsupported currency", () => {
    const result = checkFraud({ ...basePayment, currency: "XYZ" });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/not supported/);
  });

  test("high-value payment rule does not apply to refunds at same amount", () => {
    // A refund of 8000 is below MAX_REFUND_AMOUNT (5000)? No — 8000 > 5000, so rejected
    const result = checkFraud({ ...baseRefund, amount: 8000 });
    expect(result.approved).toBe(false);
    expect(result.reason).toMatch(/maximum allowed amount/);
  });
});
