# Revolut Merchant setup

The application uses Revolut Merchant Hosted Checkout. Payment amounts are created and verified server-side; the browser only receives Revolut's hosted `checkout_url`.

## Environment

Add the following values to the deployed `.env`:

```dotenv
REVOLUT_ENVIRONMENT=sandbox
REVOLUT_SECRET_KEY=sk_...
REVOLUT_WEBHOOK_SECRET=wsk_...
REVOLUT_API_VERSION=2026-08-17
REVOLUT_CURRENCY=GBP
REVOLUT_TIMEOUT=30
```

Use `REVOLUT_ENVIRONMENT=production` only after the sandbox checklist passes. Never expose the secret or webhook key in Vite/browser variables.

## Webhook

Register this HTTPS endpoint in the Revolut Business Merchant settings:

```text
https://YOUR-DOMAIN/webhooks/revolut
```

Subscribe at minimum to:

- `ORDER_COMPLETED`
- `ORDER_PAYMENT_FAILED`
- `ORDER_PAYMENT_DECLINED`
- `ORDER_CANCELLED`
- `ORDER_FAILED`

Copy the webhook signing secret into `REVOLUT_WEBHOOK_SECRET`. The endpoint validates the raw-body HMAC signature and the five-minute timestamp tolerance, then retrieves the order from Revolut before recording its state.

## Deployment

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Run a sandbox payment for every enabled service. Confirm the amount/currency, return redirect, workflow state, customer payment history, admin Payments & Invoices page, and invoice PDF. Then repeat with a small production amount before enabling production checkout broadly.
