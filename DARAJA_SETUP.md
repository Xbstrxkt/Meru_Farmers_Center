# M-Pesa Sandbox Setup

On `localhost`, checkout defaults to a clearly labeled demo flow so you can test order/booking creation without charging money. Click **Simulate successful payment** on the payment status page. This is not an M-Pesa payment and must not be used to accept real customer orders.

For actual sandbox STK Push, set `DARAJA_ENV=sandbox` and configure the Daraja credentials below. An order or booking is created only after the callback and server-side Daraja query verify the successful payment, amount, and phone number.

## Database

The `daraja_paywall_migration.sql` migration adds the payment method fields, creates `payment_transactions`, and adds payment amount/status fields to bookings. It has already been applied to the local `meru_farmers_db` database. Apply it to any other database before deploying the code; it is safe to rerun.

## Daraja configuration

Create a Daraja sandbox app and set these environment variables for the Apache process running PHP:

```text
DARAJA_ENV=sandbox
DARAJA_CONSUMER_KEY=your_sandbox_consumer_key
DARAJA_CONSUMER_SECRET=your_sandbox_consumer_secret
DARAJA_SHORTCODE=your_sandbox_shortcode
DARAJA_PASSKEY=your_sandbox_passkey
DARAJA_CALLBACK_URL=https://your-public-host/mpesa_callback.php
```

On Windows, set the variables in the environment used to launch Apache, restart Apache, and confirm PHP can read them with `getenv()`. The callback must be reachable over public HTTPS; `localhost` is not reachable by Safaricom. For local testing, use an HTTPS tunnel and set its forwarded URL ending in `/mpesa_callback.php` as `DARAJA_CALLBACK_URL`.

Use only Daraja sandbox test credentials and test numbers for this environment. The code intentionally refuses production mode until production credentials and callback security have been reviewed. Never set `DARAJA_ENV=demo` on a public or production server.

## Staff workspace and feedback

Run `administrative_modules_migration.sql` once to add review status fields to contact feedback. `staff.php` exposes modules according to the user's role; create staff accounts with the corresponding roles (`finance_manager`, `inventory_manager`, `dispatch_manager`, `driver`, `service_manager`, or `trainer`). Trainers see only assigned bookings, and drivers only assigned deliveries. Use `search.php` for global inventory, order, and service searches. Admins and service managers can review contact messages in `feedback.php`.

## Checkout behavior

- Product charges use the catalog price times the submitted quantity, calculated server-side. Daraja accepts whole shillings, so the STK amount is rounded to the nearest shilling.
- Service charges are fixed in `booking_handler.php`: KSh 2,500 for Soil Testing & Analysis, KSh 4,500 for Machinery & Tractor Hire, KSh 1,500 for Agronomy Advisory Session, and KSh 2,000 for Crop Protection Consultancy.
- The registered phone is used by default. Customers may provide a different Kenyan mobile number at checkout.
- Inventory is reduced and a paid order/booking is recorded only after a matching successful callback. An amount/phone mismatch or a post-payment fulfillment problem is flagged for staff review.