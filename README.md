# Chat Aggregator

A Laravel-based AI Chat Aggregator that provides a unified platform for interacting with multiple AI providers through a single application.

The platform is designed to allow users to subscribe to a subscription plan, access the AI models included in that plan, and interact with different AI providers through a unified backend.

The project also integrates **Stripe Billing** and **Laravel Cashier** to handle subscriptions, payments, refunds, plan changes, cancellations, and the Stripe Customer Portal.

---

## Features

### AI Integration

- Multi-provider AI integration.
- Support for multiple AI providers.
- Unified API for interacting with different AI models.
- Model selection based on the user's subscription plan.
- Centralized model configuration.

### Subscription System

- Subscription plans.
- Monthly and yearly billing.
- One active subscription plan per user.
- Subscription status tracking.
- Change subscription plan.
- Cancel subscription.
- Resume subscription during the grace period.
- Current subscription API.
- Subscription-plan/model relationships.

### Stripe Integration

- Stripe Checkout.
- Stripe Billing.
- Laravel Cashier.
- Stripe Customer creation.
- Stripe subscriptions.
- Stripe prices and products.
- Stripe Webhooks.
- Payment tracking.
- Failed payment tracking.
- Refund tracking.
- Customer Portal.
- Subscription cancellation.
- Subscription plan changes.
- Payment method management.

---

# Tech Stack

- PHP 8.4
- Laravel 11
- MySQL
- Laravel Cashier
- Stripe
- Stripe PHP SDK
- Laravel Sanctum
- Guzzle
- React
- Tailwind CSS

### Supported AI Providers

- OpenAI
- Google Gemini
- Anthropic Claude
- DeepSeek

---

# Requirements

Before running the project, make sure the following are installed:

- PHP 8.4+
- Composer
- MySQL
- Node.js
- npm
- Laravel
- Stripe CLI
- Git

---

# Installation

## 1. Clone the repository

```bash
git clone https://github.com/Moazjawish/chat-aggregator.git
cd Chat_Aggregator
```

---

## 2. Install PHP dependencies

```bash
composer install
```

---

## 3. Install frontend dependencies

```bash
npm install
```

---

## 4. Create the environment file

Windows:

```powershell
copy .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

---

## 5. Generate the application key

```bash
php artisan key:generate
```

---

# Database Configuration

Configure your MySQL database in `.env`.

Example:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat_aggregator
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate
```

If you want to recreate the database and run seeders:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh --seed` deletes all existing database tables and recreates them. Do not use it on a production database.

---

# Stripe Configuration

The project uses Stripe in **Test Mode** during development.

Configure the Stripe keys in `.env`:

```env
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

Laravel Cashier should use the same Stripe secret key.

---

# Stripe Test Mode

Make sure **Test Mode** is enabled in Stripe Dashboard.

Do not use real cards while testing.

A common successful Stripe test card is:

```text
4242 4242 4242 4242
```

Use:

```text
Expiry: Any future date
CVC: 123
ZIP: 12345
```

For testing a declined payment:

```text
4000 0000 0000 0002
```

---

# Stripe CLI

The application receives Stripe events through a Laravel webhook endpoint.

Start the Laravel application:

```bash
php artisan serve
```

The application should normally be available at:

```text
http://127.0.0.1:8000
```

Then start Stripe CLI:

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

Stripe CLI will display a webhook signing secret.

Example:

```text
Ready! Your webhook signing secret is whsec_xxxxxxxxx
```

Add that value to:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxx
```

Then clear Laravel configuration:

```bash
php artisan optimize:clear
```

---

# Stripe Webhook

The application exposes:

```http
POST /api/stripe/webhook
```

Stripe events are forwarded to this endpoint.

The webhook controller extends Laravel Cashier's webhook controller.

Important events handled by the application include:

```text
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted

invoice.payment_succeeded
invoice.payment_failed

charge.refunded
```

The webhook is responsible for synchronizing Stripe events with the local database.

---

# Important Database Structure

## Users

The `users` table contains the application user and Stripe customer information.

Important Stripe fields include:

```text
stripe_customer_id
stripe_id
pm_type
pm_last_four
trial_ends_at
```

---

## Subscription Plans

The `subscription_plans` table represents plans offered by the application.

Example:

```text
Basic
Pro
```

Important fields:

```text
id
name
description
price
currency
billing_interval
stripe_product_id
stripe_price_id
status
```

Each plan represents a complete subscription option.

---

## Subscriptions

The `subscriptions` table is based on Laravel Cashier.

Important fields:

```text
user_id
subscription_plan_id
type
stripe_id
stripe_status
stripe_price
quantity
trial_ends_at
ends_at
```

The application is designed around:

```text
One User
    ↓
One Active Subscription
    ↓
One Subscription Plan
```

For example:

```text
User
 ↓
Basic Plan
```

or:

```text
User
 ↓
Pro Plan
```

---

## Payments

The `payments` table stores payment information associated with users and subscriptions.

Important fields:

```text
user_id
subscription_id
stripe_payment_intent_id
stripe_checkout_session_id
stripe_invoice_id
amount
currency
status
paid_at
```

Possible application statuses include:

```text
pending
paid
failed
refunded
```

---

## AI Models

The `models` table contains the AI models available in the platform.

Example:

```text
GPT
Gemini
Claude
DeepSeek
```

Important fields:

```text
name
provider
model_key
status
```

---

## Subscription Plan Models

The `subscription_plan_model` pivot table determines which AI models are available for each subscription plan.

Relationship:

```text
SubscriptionPlan
       ↓
subscription_plan_model
       ↓
AIModel
```

Example:

```text
Basic
 ├── GPT
 └── Gemini

Pro
 ├── GPT
 ├── Gemini
 ├── Claude
 └── DeepSeek
```

---

# API Authentication

The API uses Laravel Sanctum.

Authenticated requests should include:

```http
Authorization: Bearer YOUR_TOKEN
```

---

# API Testing

The following examples can be tested using:

- Postman
- React frontend

---

# 1. Register

Example:

```http
POST /api/register
```

Example JSON:

```json
{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password",
    "password_confirmation": "password"
}
```

Save the returned authentication token.

---

# 2. Login

```http
POST /api/login
```

Example:

```json
{
    "email": "test@example.com",
    "password": "password"
}
```

Use the returned token for authenticated requests.

---

# 3. Get Current Subscription

```http
GET /api/subscription/current
```

Header:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

This endpoint returns the user's current subscription and subscription plan.

Example response:

```json
{
    "subscription": {
        "id": 1,
        "stripe_id": "sub_xxxxx",
        "stripe_status": "active"
    },
    "plan": {
        "id": 1,
        "name": "Basic",
        "price": "10.00"
    }
}
```

---

# 4. Subscribe to a Plan

The application creates the Stripe subscription using the Stripe Price associated with the selected subscription plan.

The exact endpoint depends on the subscription controller implementation.

---

# 5. Change Subscription Plan

The application allows the user to change the complete subscription plan.

Example:

```http
POST /api/subscription/change-plan
```

Example JSON:

```json
{
    "subscription_plan_id": 2
}
```

The backend:

1. Finds the authenticated user's active subscription.
2. Finds the new subscription plan.
3. Validates the new Stripe Price.
4. Calls Cashier's `swap()` method.
5. Updates the local `subscription_plan_id`.
6. Updates Stripe metadata.
7. Stripe sends `customer.subscription.updated`.
8. Laravel processes the webhook.

---

# 6. Cancel Subscription

Example:

```http
POST /api/subscription/cancel
```

The subscription is canceled according to the configured Cashier behavior.

During the grace period, the subscription may still be usable.

Cashier provides:

```php
$subscription->onGracePeriod()
```

to determine whether the canceled subscription is still within its grace period.

---

# 7. Resume Subscription

If the subscription has been canceled but is still inside its grace period:

```http
POST /api/subscription/resume
```

The subscription can be resumed.

---

# 8. Customer Portal

The application can generate a Stripe Customer Portal session for the authenticated user.

The user can use the Stripe Customer Portal to:

- View subscription information.
- Update payment methods.
- View invoices.
- Manage billing information.
- Cancel subscriptions.
- Manage their Stripe billing information.

The backend generates the Customer Portal session using the authenticated user's Stripe customer.

---

# Testing Stripe Payments

## Successful Payment

Use:

```text
4242 4242 4242 4242
```

Verify the `payments` table:

```text
status = paid
paid_at != NULL
```

---

# Testing Failed Payment

Use:

```text
4000 0000 0000 0002
```

Expected result:

```text
Payment attempt
 ↓
Stripe declines payment
 ↓
invoice.payment_failed
 ↓
Laravel Webhook
 ↓
Payment status = failed
```

Expected database value:

```text
status = failed
paid_at = NULL
```

Stripe CLI should display:

```text
invoice.payment_failed
```

and:

```text
<-- [200] POST http://127.0.0.1:8000/api/stripe/webhook
```

The HTTP `200` confirms that the webhook endpoint successfully responded to Stripe.

---

# Testing Refund

First create a successful payment using:

```text
4242 4242 4242 4242
```

Then create a refund from Stripe Test Mode.

Expected events include:

```text
refund.created
charge.refunded
refund.updated
charge.refund.updated
```

The application processes:

```text
charge.refunded
```

Expected database result:

```text
payments.status = refunded
```

---

# Monitoring Webhooks

Laravel logs are located at:

```text
storage/logs/laravel.log
```

On Windows PowerShell:

```powershell
Get-Content storage/logs/laravel.log -Wait
```

This is useful when testing Stripe webhooks.

---

# Useful Laravel Commands

Clear Laravel cache:

```bash
php artisan optimize:clear
```

Run migrations:

```bash
php artisan migrate
```

Recreate database:

```bash
php artisan migrate:fresh --seed
```

Start Laravel:

```bash
php artisan serve
```

Check routes:

```bash
php artisan route:list
```

---

# Recommended Development Workflow

Run Laravel:

```bash
php artisan serve
```

Run Stripe CLI in another terminal:

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

Run frontend:

```bash
npm run dev
```

Then test the application using Postman or the React frontend.

---

# Stripe Testing Checklist

Before considering Stripe integration complete, verify:

```text
[✓] Stripe Test Mode
[✓] Stripe Customer created
[✓] Stripe Product created
[✓] Stripe Price created
[✓] Checkout works
[✓] Subscription created
[✓] customer.subscription.created
[✓] Subscription linked to local SubscriptionPlan
[✓] Payment created
[✓] invoice.payment_succeeded
[✓] Payment status = paid
[✓] Current subscription API
[✓] Change Plan
[✓] customer.subscription.updated
[✓] Cancel Subscription
[✓] Resume Subscription
[✓] Customer Portal
[✓] Payment Failed
[✓] invoice.payment_failed
[✓] Refund
[✓] charge.refunded
[✓] Payment status = refunded
[✓] Stripe Webhook returns HTTP 200
```

---

# Git Workflow

After completing the changes, check the modified files:

```bash
git status
```

Review the changes:

```bash
git diff
```

Add the changes:

```bash
git add .
```

Create a commit:

```bash
git commit -m "Complete Stripe subscription and payment integration"
```

Push to the repository:

```bash
git push origin main
```

If your main branch has another name, check it using:

```bash
git branch
```

Then push to the correct branch.

---

# Important Security Notes

Never commit `.env` to the repository.

Make sure `.gitignore` contains:

```text
.env
/vendor/
/node_modules/
```

Never publish:

```text
STRIPE_SECRET
STRIPE_WEBHOOK_SECRET
OPENAI_API_KEY
GEMINI_API_KEY
ANTHROPIC_API_KEY
DEEPSEEK_API_KEY
```

Only `.env.example` should contain the variable names without the real secrets.

Example:

```env
APP_NAME=
APP_KEY=

DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

OPENAI_API_KEY=
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
DEEPSEEK_API_KEY=
```

---

# Future Development

The next major part of the project is AI usage and cost tracking.

The planned flow is:

```text
User
 ↓
Subscription Plan
 ↓
Allowed AI Models
 ↓
Chat Request
 ↓
Input Tokens
Output Tokens
 ↓
Provider Cost
 ↓
Application Price
 ↓
Profit
```

Future features may include:

- AI usage tracking.
- Token usage tracking.
- Provider cost calculation.
- Profit calculation.
- Usage limits.
- File uploads.
- Image uploads.
- AI vision.
- Usage dashboards.
- Admin billing dashboard.
- Advanced analytics.

---

# License

This project is currently under development.
