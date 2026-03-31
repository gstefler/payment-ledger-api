export interface TransactionCheckRequest {
  transaction_id: string;
  merchant_id: string;
  type: "payment" | "refund";
  amount: number;
  currency: string;
  idempotency_key: string;
}

export interface FraudCheckResult {
  approved: boolean;
  reason?: string;
}
