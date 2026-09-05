# Chat Aggregator

Chat Aggregator is a Laravel-based backend platform that allows users to interact with multiple AI providers and models through a unified chat system.

The platform supports:

- Multiple AI providers
- Multiple AI models
- Subscription plans
- Model-based pricing
- Token usage limits
- File and image attachments
- Conversation history
- Model switching inside the same conversation
- Stripe payments and subscriptions
- Usage and cost tracking
- Feature-based access control
- Model capability control

The project is designed to serve as the backend API for a React frontend.

---

# Project Overview

The main goal of the system is to provide users with one interface where they can interact with different AI models based on their active subscription plan.

A user can:

1. Register and log in.
2. Subscribe to a plan.
3. Access models included in the plan.
4. Start conversations.
5. Switch between AI models inside the same conversation.
6. Upload images or documents.
7. Send files to supported AI models.
8. Track token usage.
9. Track remaining usage limits.
10. Upgrade or change subscription plans.
11. View payment history.
12. Cancel or resume subscriptions.

---

# Main Architecture

The main request flow is:

```text
User
   ↓
Authentication
   ↓
Subscription
   ↓
Subscription Plan
   ↓
Features + Models
   ↓
Conversation
   ↓
Message
   ↓
Attachments
   ↓
AI Service
   ↓
Provider Service
   ↓
AI Provider API
   ↓
Response
   ↓
Token Usage
   ↓
Cost Calculation
   ↓
Database
```

---

# Technology Stack

## Backend

- PHP 8.4
- Laravel 11
- Laravel Sanctum
- Laravel Cashier
- MySQL
- Redis / Laravel Cache Locks
- Guzzle HTTP Client

## AI Providers

The project supports multiple AI providers through dedicated services.

- OpenAI
- Google Gemini
- Anthropic Claude
- DeepSeek

Each provider implements the same common interface.

```php
interface AIProviderInterface
{
    public function chat(
        string $model,
        array $messages,
        array $attachments = []
    ): array;
}
```

---

# Frontend

The backend is designed to work with a React frontend.

The frontend can use the API to:

- authenticate users
- display available models
- manage subscriptions
- create conversations
- display conversation history
- upload attachments
- send messages
- display token usage
- display remaining limits
- manage billing

---

# Installation

## 1. Clone the repository

```bash
git clone https://github.com/Moazjawish/chat-aggregator.git
```

Enter the project directory:

```bash
cd Chat_Aggregator
```

---

## 2. Install PHP dependencies

```bash
composer install
```

---

## 3. Create environment file

```bash
cp .env.example .env
```

On Windows:

```powershell
copy .env.example .env
```

---

## 4. Generate application key

```bash
php artisan key:generate
```

---

# Database Configuration

Create a MySQL database.

Example:

```text
chat_aggregator
```

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat_aggregator
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations:

```bash
php artisan migrate
```

Run seeders:

```bash
php artisan db:seed
```

Or:

```bash
php artisan migrate:fresh --seed
```

Use `migrate:fresh` only in development because it deletes existing data.

---

# Running the Application

Start Laravel:

```bash
php artisan serve
```

Default URL:

```text
http://127.0.0.1:8000
```

API base URL:

```text
http://127.0.0.1:8000/api
```

---

# Authentication

Authentication is handled using Laravel Sanctum.

Public routes include:

```text
POST /api/register
POST /api/login
POST /api/stripe/webhook
```

Authenticated routes require:

```http
Authorization: Bearer YOUR_TOKEN
```

Recommended headers:

```text
Accept: application/json
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN
```

---

# Main Database Structure

## users

Stores application users.

The user is connected to:

- subscriptions
- conversations
- files
- usage
- payments

---

# Subscription Plans

The `subscription_plans` table represents the plans available to users.

Typical fields include:

```text
id
name
description
price
currency
billing_interval
duration_days
stripe_product_id
stripe_price_id
status
```

Example plans:

```text
Basic
Pro
Premium
```

---

# Models

The `models` table stores AI models supported by the platform.

Typical fields:

```text
id
name
provider
model_key
status
```

Example:

```text
GPT
Gemini
Claude
DeepSeek
```

`model_key` contains the identifier used by the provider API.

Example:

```text
gpt-...
gemini-...
claude-...
deepseek-...
```

---

# Subscription Plan Models

Plans and models use a many-to-many relationship.

Pivot table:

```text
subscription_plan_model
```

This table contains:

```text
subscription_plan_id
model_id
input_price
output_price
input_token_limit
output_token_limit
status
```

This allows the same AI model to have different:

- pricing
- limits
- availability

depending on the subscription plan.

---

# Provider Costs

Provider costs are stored separately from user-facing prices.

Table:

```text
model_costs
```

Typical fields:

```text
model_id
input_cost
output_cost
effective_from
effective_to
```

This represents the actual cost paid to the AI provider.

User prices are stored separately in:

```text
subscription_plan_model
```

Therefore:

```text
Provider Cost
+
Profit Margin
=
User Price
```

---

# Cost Calculation

Token prices are calculated per 1,000,000 tokens.

Example:

```php
$inputCost =
    ($inputTokens / 1_000_000)
    * $inputPrice;

$outputCost =
    ($outputTokens / 1_000_000)
    * $outputPrice;
```

Total:

```php
$totalCost =
    $inputCost
    +
    $outputCost;
```

Two types of cost are stored:

```text
provider_cost
user_cost
```

`provider_cost` represents the actual provider cost.

`user_cost` represents the amount charged according to the subscription plan pricing.

---

# Features

The system contains a dynamic feature system.

Table:

```text
features
```

Fields:

```text
id
name
key
description
status
```

Current feature examples:

```text
file_upload
image_upload
web_search
advanced_models
```

Plans are connected to features using:

```text
subscription_plan_feature
```

Fields include:

```text
subscription_plan_id
feature_id
status
```

This means each subscription plan can independently enable or disable system features.

---

# Feature Checking

Features are checked through `FeatureService`.

Example:

```php
$featureService->has(
    $user,
    'file_upload'
);
```

This verifies:

1. user has a subscription
2. subscription is valid
3. subscription has a current plan
4. the plan contains the requested active feature

---

# Feature Middleware

Laravel middleware can be used for routes that require a specific feature.

Example:

```php
->middleware('feature:web_search')
```

The middleware alias is registered in:

```text
bootstrap/app.php
```

Example:

```php
$middleware->alias([
    'feature' => FeatureMiddleware::class,
]);
```

File uploads currently perform feature checks dynamically inside the controller because the same endpoint handles both:

```text
image_upload
file_upload
```

---

# Model Capabilities

Plan Features and Model Capabilities are different concepts.

A Plan Feature answers:

```text
Is the user allowed to use this feature?
```

A Model Capability answers:

```text
Can this AI model technically process this type of input?
```

Capabilities are stored in:

```text
model_capabilities
```

Fields:

```text
model_id
key
status
```

Current capabilities include:

```text
image_input
document_input
```

Example:

```text
Plan supports image_upload
+
Model supports image_input
=
User can send images to that model
```

Both conditions must be true.

---

# Conversations

Conversations are stored in:

```text
conversations
```

Typical fields:

```text
id
user_id
title
created_at
updated_at
```

A conversation is not permanently connected to one AI model.

This allows users to switch models inside the same conversation.

Example:

```text
Conversation
├── User → Gemini
├── Assistant → Gemini
├── User → GPT
├── Assistant → GPT
└── User → Claude
```

Each individual message stores the selected model.

---

# Messages

Messages are stored in:

```text
messages
```

Important fields:

```text
conversation_id
model_id
role
content
input_tokens
output_tokens
provider_cost
user_cost
created_at
updated_at
```

Roles currently include:

```text
user
assistant
```

The `model_id` is stored on both user and assistant messages.

This makes it possible to know exactly which model was selected for every request.

---

# Conversation History

Before sending a new request, the system loads previous messages:

```text
Conversation
   ↓
Messages
   ↓
AI Provider
```

This allows AI models to receive previous conversation context.

When the user switches models, the new model also receives the previous conversation history.

---

# File Uploads

Files are uploaded separately from chat requests.

Endpoint:

```text
POST /api/files
```

The endpoint accepts multipart form data.

Example:

```text
file = document.pdf
```

Supported types currently include:

```text
pdf
txt
doc
docx
jpg
jpeg
png
webp
```

Default maximum size:

```text
10 MB
```

---

# File Upload Flow

Files are not uploaded directly inside `/api/chat`.

The process is:

```text
POST /api/files
        ↓
Store file
        ↓
Create files record
        ↓
Return file ID
        ↓
POST /api/chat
        ↓
Send file_ids
```

Example response:

```json
{
    "attachment": {
        "id": 12,
        "type": "document"
    }
}
```

Chat request:

```json
{
    "model_id": 3,
    "message": "Explain this document",
    "file_ids": [12]
}
```

---

# Files Table

The `files` table stores attachment information.

Fields include:

```text
id
user_id
conversation_id
original_name
path
disk
mime_type
extension
size
status
extracted_text
processing_error
created_at
updated_at
```

Files are currently sent directly to AI providers.

Manual text extraction is not required for the main attachment flow.

---

# Message Attachments

Files are connected to exact messages using:

```text
message_file
```

This is a many-to-many relationship between:

```text
messages
files
```

Example:

```text
Message 25
├── CV.pdf
└── photo.png
```

Pivot fields:

```text
message_id
file_id
created_at
updated_at
```

---

# Attachment History

Attachments are preserved as part of conversation context.

Example:

```text
Message 1
"Explain this PDF"
└── report.pdf

Message 2
"What is the most important result?"
```

The second request can still access the previous attachment even if `file_ids` is not sent again.

Historical attachments are loaded from:

```text
message_file
```

and passed again to the current AI provider when required.

---

# Switching Models With Attachments

When a user changes models inside a conversation, the system checks whether the new model supports all required historical attachment types.

Example:

```text
Gemini
document_input = true

↓ switch

Model B
document_input = false
```

The request is rejected rather than sending an unsupported attachment.

Example response:

```json
{
    "message": "The selected model does not support document input."
}
```

---

# AI Service

`AIService` is the central service responsible for chat processing.

Responsibilities include:

1. validating the user
2. validating the conversation
3. validating the subscription
4. finding the current subscription plan
5. checking model availability
6. validating attachments
7. checking plan features
8. checking model capabilities
9. checking usage limits
10. loading conversation history
11. loading historical attachments
12. selecting the AI provider
13. sending the API request
14. receiving actual token usage
15. calculating costs
16. storing messages
17. linking attachments
18. recording model usage

---

# Provider Services

Provider-specific logic is separated into different services.

Example:

```text
AIService
├── OpenAIService
├── GoogleAIService
├── ClaudeService
└── DeepSeekService
```

The service is selected using the model provider.

Example:

```php
return match (
    strtolower($model->provider)
) {
    'openai' =>
        $this->openAIService->chat(...),

    'google',
    'gemini' =>
        $this->googleAIService->chat(...),

    'anthropic',
    'claude' =>
        $this->claudeService->chat(...),

    'deepseek' =>
        $this->deepSeekService->chat(...),

    default =>
        throw new RuntimeException(
            'Unsupported AI provider.'
        ),
};
```

---

# Google Gemini Attachments

Gemini supports direct file processing.

PDF files are uploaded through Gemini Files API.

The application waits for Gemini to process the file before sending it to the model.

Images are sent as inline binary data.

This allows the AI model to directly process:

```text
PDF
Images
```

without manually extracting document text.

---

# OpenAI Attachments

The OpenAI service supports multimodal input through the Responses API.

Depending on the model, attachments may be sent as:

```text
input_image
input_file
```

The backend checks the model capability before sending attachments.

---

# Claude Attachments

Claude service supports multimodal content.

Images are sent using base64 sources.

PDF support can also be handled as document content for compatible Claude models.

---

# DeepSeek

DeepSeek requests are sent through the provider chat API.

Attachments are allowed only when the configured model supports the corresponding capability.

Unsupported attachment types are rejected before the request reaches the provider.

---

# Usage Tracking

Every successful AI request creates a usage record.

Table:

```text
model_usages
```

Fields include:

```text
user_id
subscription_id
model_id
input_tokens
output_tokens
total_provider_cost
total_user_cost
created_at
updated_at
```

Usage is stored per:

```text
subscription
+
model
+
billing period
```

---

# Usage Limits

Limits are defined in:

```text
subscription_plan_model
```

Fields:

```text
input_token_limit
output_token_limit
```

Example:

```text
Input limit: 100000
Output limit: 50000
```

The backend calculates:

```text
used
limit
remaining
can_use
```

---

# Billing Period Usage

Usage is not deleted when a subscription renews.

Instead, the subscription contains:

```text
current_period_start
current_period_end
```

Only usage records within the current billing period are counted.

Example:

```text
Subscription ID = 10

Period 1
2026-08-05 → 2026-09-05

Period 2
2026-09-05 → 2026-10-05
```

The same subscription can continue, while token limits reset logically for each new period.

Old usage remains stored for reporting and cost analysis.

---

# Usage Query Logic

Usage is filtered approximately as:

```sql
WHERE subscription_id = ?
AND model_id = ?
AND created_at >= current_period_start
AND created_at < current_period_end
```

This means there is no need to delete or reset old usage rows.

---

# Concurrent Usage Protection

The application uses Laravel Cache Locks to prevent concurrent requests from bypassing token limits.

Lock key format:

```text
ai-usage:subscription:{subscription_id}:model:{model_id}
```

Example:

```php
Cache::lock(
    $lockKey,
    120
);
```

The usage check is performed inside the lock.

This prevents two simultaneous requests from reading the same old usage amount and both passing the limit check.

---

# Stripe Integration

Payments and subscriptions are handled using:

```text
Stripe
Laravel Cashier
Stripe CLI
```

---

# Stripe Configuration

Add Stripe keys to `.env`:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Depending on the Cashier configuration, additional variables may be required.

---

# Stripe Webhook

Webhook route:

```text
POST /api/stripe/webhook
```

For local development:

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

Stripe CLI will return a webhook signing secret.

Add it to `.env`.

Example:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

Then clear cached configuration:

```bash
php artisan optimize:clear
```

---

# Stripe Events

The application handles events including:

```text
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted

invoice.payment_succeeded
invoice.payment_failed

charge.refunded

customer.updated
customer.deleted
```

---

# Subscription Creation

When Stripe sends:

```text
customer.subscription.created
```

Laravel Cashier first creates or synchronizes the local subscription.

The custom webhook controller then connects the subscription with:

```text
subscription_plan_id
```

using Stripe metadata.

---

# Changing Subscription Plans

The subscription contains two local plan fields:

```text
subscription_plan_id
pending_subscription_plan_id
```

`subscription_plan_id` is the currently active plan.

`pending_subscription_plan_id` represents a requested plan change waiting for successful payment.

---

# Safe Plan Change Flow

The flow is:

```text
Current Plan
     ↓
User requests new plan
     ↓
pending_subscription_plan_id = New Plan
     ↓
Stripe subscription price changes
     ↓
Invoice generated
     ↓
Payment succeeds
     ↓
invoice.payment_succeeded
     ↓
Verify Stripe Price
     ↓
subscription_plan_id = New Plan
     ↓
pending_subscription_plan_id = NULL
```

The plan is not activated during:

```text
customer.subscription.updated
```

because a Stripe subscription update does not guarantee successful payment.

---

# Failed Plan Changes

When Stripe sends:

```text
invoice.payment_failed
```

the application:

```text
keeps subscription_plan_id unchanged
clears pending_subscription_plan_id
records failed payment
```

This prevents the user from receiving access to a plan that was not successfully paid.

---

# Payments

Payments are stored in:

```text
payments
```

Typical fields:

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

Possible statuses include:

```text
paid
failed
refunded
partially_refunded
```

---

# Refunds

Stripe refund events are handled through:

```text
charge.refunded
```

If the entire payment was refunded:

```text
status = refunded
```

If only part of the payment was refunded:

```text
status = partially_refunded
```

---

# Stripe Billing Period Synchronization

The local subscription stores:

```text
current_period_start
current_period_end
```

With Stripe Basil API versions, these values are obtained from the first subscription item:

```text
subscription
└── items
    └── data[0]
        ├── current_period_start
        └── current_period_end
```

These values are synchronized during relevant Stripe subscription and payment events.

---

# Main API Routes

## Authentication

```text
POST /api/register
POST /api/login
POST /api/logout
```

---

## Subscription Plans

```text
POST /api/plans
```

Depending on route naming in the frontend, this can be used to retrieve plan information.

---

## Subscription

```text
GET  /api/subscription
POST /api/subscription/checkout
POST /api/subscription/change-plan
POST /api/subscription/cancel
POST /api/subscription/resume
```

---

## Billing

Billing portal endpoints are also available for managing Stripe billing where configured.

---

## Payments

```text
GET /api/payments
```

---

## Usage

```text
GET /api/usage
```

Returns current billing-period usage for all models available in the user's current plan.

---

## Models

```text
GET /api/models
```

Returns models available in the user's current subscription plan.

Model responses can also contain capabilities such as:

```json
{
    "capabilities": {
        "document_input": true,
        "image_input": true
    }
}
```

---

# Conversations

Typical conversation routes:

```text
GET    /api/conversations
POST   /api/conversations
GET    /api/conversations/{id}
PUT    /api/conversations/{id}
DELETE /api/conversations/{id}
```

---

# Files

```text
POST /api/files
```

Uses:

```text
multipart/form-data
```

Example:

```text
file = report.pdf
conversation_id = optional
```

---

# Chat

```text
POST /api/chat
```

Example request:

```json
{
    "conversation_id": 7,
    "model_id": 3,
    "message": "Explain this document",
    "file_ids": [12]
}
```

`conversation_id` may be optional depending on the controller flow.

If no conversation exists, the backend can create a new conversation.

---

# Example Chat Without Attachment

```json
{
    "model_id": 3,
    "message": "Explain dependency injection in Laravel"
}
```

---

# Example Chat With PDF

First upload:

```text
POST /api/files
```

Then:

```json
{
    "conversation_id": 7,
    "model_id": 3,
    "message": "Summarize this PDF",
    "file_ids": [12]
}
```

---

# Example Follow-Up Without file_ids

After the PDF was connected to a previous message:

```json
{
    "conversation_id": 7,
    "model_id": 3,
    "message": "What is the most important point in the document?"
}
```

The backend loads the historical attachment from the conversation context.

---

# Conversation Response

A conversation response can contain:

```json
{
    "conversation": {
        "id": 7,
        "title": "Explain this document"
    },
    "messages": [
        {
            "id": 25,
            "role": "user",
            "content": "Explain this document",
            "model": {
                "id": 3,
                "name": "Gemini",
                "provider": "gemini"
            },
            "attachments": [
                {
                    "id": 12,
                    "type": "document",
                    "original_name": "report.pdf",
                    "mime_type": "application/pdf",
                    "extension": "pdf"
                }
            ]
        }
    ]
}
```

---

# Usage Response

Example:

```json
{
    "subscription": {
        "id": 8,
        "plan": {
            "id": 2,
            "name": "Pro"
        },
        "current_period_start": "2026-09-05T15:00:00Z",
        "current_period_end": "2026-10-05T15:00:00Z"
    },
    "models": [
        {
            "model": {
                "id": 3,
                "name": "Gemini",
                "provider": "gemini"
            },
            "can_use": true,
            "input": {
                "used": 15430,
                "limit": 100000,
                "remaining": 84570
            },
            "output": {
                "used": 3240,
                "limit": 50000,
                "remaining": 46760
            }
        }
    ]
}
```

---

# Error Responses

## Unauthenticated

```json
{
    "message": "Unauthenticated."
}
```

HTTP:

```text
401
```

---

## Model Not Available

```json
{
    "message": "This model is not available in your current subscription plan."
}
```

HTTP:

```text
403
```

---

## Feature Not Available

```json
{
    "message": "Your current plan does not support document uploads."
}
```

HTTP:

```text
403
```

---

## Model Capability Not Available

```json
{
    "message": "The selected model does not support document input."
}
```

HTTP:

```text
403
```

---

## Usage Limit Reached

```json
{
    "message": "You have reached the usage limit for this model."
}
```

HTTP:

```text
422
```

---

## Concurrent AI Request

```json
{
    "message": "Another request for this model is currently being processed. Please try again."
}
```

HTTP:

```text
429
```

---

## AI Provider Error

Production example:

```json
{
    "message": "The AI provider is temporarily unavailable. Please try again later."
}
```

During local development, actual provider errors may be shown when:

```env
APP_DEBUG=true
```

---

# Development Debugging

Watch Laravel logs:

```powershell
Get-Content storage/logs/laravel.log -Wait
```

Linux/macOS:

```bash
tail -f storage/logs/laravel.log
```

---

# Clear Laravel Cache

After changing `.env` or configuration:

```bash
php artisan optimize:clear
```

---

# Useful Artisan Commands

Run migrations:

```bash
php artisan migrate
```

Run seeders:

```bash
php artisan db:seed
```

Run a specific seeder:

```bash
php artisan db:seed --class=ModelCapabilitySeeder
```

Clear cache:

```bash
php artisan optimize:clear
```

Start server:

```bash
php artisan serve
```

---

# Testing Stripe Locally

Login to Stripe CLI:

```bash
stripe login
```

Start webhook listener:

```bash
stripe listen --forward-to http://127.0.0.1:8000/api/stripe/webhook
```

Keep the Stripe CLI terminal running while testing payments.

---

# Testing With Postman

Recommended workflow:

```text
1. Register
2. Login
3. Copy Bearer Token
4. Retrieve plans
5. Subscribe
6. Verify subscription
7. Retrieve models
8. Create conversation
9. Send text chat
10. Upload PDF/image
11. Send chat with file_ids
12. Send follow-up without file_ids
13. Switch AI model
14. Check conversation history
15. Check usage
16. Check payments
17. Test subscription change
18. Test cancellation/resume
```

---

# Storage

Uploaded files are stored using Laravel Storage.

Example directories:

```text
users/{user_id}/files
users/{user_id}/images
```

The database stores the relative path and disk.

Absolute server paths are never returned to the frontend.

---

# Security

The backend includes several security controls:

- Sanctum authentication
- user ownership checks
- conversation ownership validation
- file ownership validation
- subscription validation
- plan feature validation
- model capability validation
- model plan access validation
- token usage enforcement
- Redis atomic locks
- Stripe webhook signature handling through Cashier
- no exposure of local filesystem paths to frontend

---

# Design Principles

## Provider Independence

The application is not tightly coupled to a single AI provider.

Provider-specific implementation exists only inside dedicated services.

---

## Subscription Independence

AI model configuration is separated from subscription pricing.

A model can belong to multiple plans with different:

```text
prices
token limits
availability
```

---

## Cost Separation

Provider cost and customer cost are stored independently.

This makes it possible to:

- calculate profit margins
- change plan pricing
- analyze provider expenses
- generate financial reports

---

## Historical Usage Preservation

Usage records are never deleted when a billing cycle renews.

Billing periods determine which usage records count toward the current limit.

This preserves historical financial and analytical data.

---

## Attachment Persistence

Attachments are connected to:

```text
User
Conversation
Message
```

This allows the backend to understand exactly which files were used in which message while still preserving them as part of future conversation context.

---

# Current Backend Status

The main backend MVP is implemented.

Completed areas include:

```text
Authentication
Subscriptions
Stripe payments
Plan changes
Payment failure handling
Refund handling
Billing periods
Plans
Models
Features
Model capabilities
Conversations
Messages
Attachments
Attachment history
Model switching
AI providers
Usage tracking
Token limits
Provider cost
User cost
Concurrency protection
```

---

# Possible Future Improvements

The following features can be added later.

## Web Search

The `web_search` feature already exists in the feature system and can later be connected to supported AI models or external search providers.

---

## Advanced File Limits

Different plans can have limits such as:

```text
maximum file size
maximum number of files
storage limit
files per message
```

---

## File Lifecycle Management

Possible future improvements:

```text
delete physical file when database record is deleted
scheduled cleanup for unused uploads
temporary upload expiration
cloud storage support
```

---

## Admin Dashboard

Possible admin functionality:

```text
Manage users
Manage subscription plans
Manage models
Manage model pricing
Manage provider costs
Manage features
Manage capabilities
View payments
View provider expenses
View platform revenue
View token usage
```

---

## Financial Reports

Because the system stores:

```text
provider cost
user cost
token usage
payments
```

future reporting can calculate:

```text
Revenue
Provider Expenses
Gross Profit
Profit Margin
Cost per User
Cost per Model
Cost per Plan
```

---

## Automated Tests

Recommended future testing:

```text
Feature Tests
Unit Tests
Stripe Webhook Tests
Usage Limit Tests
Attachment Tests
Model Switching Tests
Authorization Tests
```

---

# Project Structure

Important project areas:

```text
app/
├── Exceptions/
│
├── Http/
│   ├── Controllers/
│   │   ├── ChatController.php
│   │   ├── ConversationController.php
│   │   ├── FileController.php
│   │   ├── UsageController.php
│   │   ├── StripeWebhookController.php
│   │   └── ...
│   │
│   └── Middleware/
│       └── FeatureMiddleware.php
│
├── Models/
│   ├── User.php
│   ├── Subscription.php
│   ├── SubscriptionPlan.php
│   ├── AIModel.php
│   ├── ModelCost.php
│   ├── ModelUsage.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── File.php
│   ├── Feature.php
│   └── ModelCapability.php
│
└── Services/
    └── AI/
        ├── AIService.php
        ├── AIProviderInterface.php
        ├── OpenAIService.php
        ├── GoogleAIService.php
        ├── ClaudeService.php
        ├── DeepSeekService.php
        └── UsageService.php
```

---

# Main Database Relationships

```text
User
├── Subscriptions
├── Conversations
├── Files
├── Payments
└── ModelUsages


SubscriptionPlan
├── Models
│     └── subscription_plan_model
│
└── Features
      └── subscription_plan_feature


AIModel
├── ModelCosts
├── ModelCapabilities
└── ModelUsages


Conversation
├── Messages
└── Files


Message
└── Files
      └── message_file
```

---

# Environment Variables

Example `.env` configuration:

```env
APP_NAME="Chat Aggregator"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000


DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat_aggregator
DB_USERNAME=root
DB_PASSWORD=


CACHE_STORE=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379


STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=


OPENAI_API_KEY=

GEMINI_API_KEY=

ANTHROPIC_API_KEY=

DEEPSEEK_API_KEY=
```

The exact environment variable names should match the project's service configuration files.

Never commit real API keys to Git.

---

# Important Security Note

Do not commit:

```text
.env
API Keys
Stripe Secret Keys
Webhook Secrets
Database Passwords
Private certificates
```

Ensure `.env` is included in `.gitignore`.

---

# Production Preparation

Before production deployment:

```env
APP_ENV=production
APP_DEBUG=false
```

Then run:

```bash
php artisan optimize
```

Recommended production services include:

```text
Nginx
PHP-FPM
MySQL
Redis
HTTPS
Queue Worker
Supervisor/Systemd
```

Also configure Stripe's production webhook endpoint to:

```text
https://YOUR_DOMAIN/api/stripe/webhook
```

---

# Current Development Stage

The backend MVP is considered complete for the core Chat Aggregator workflow.

The next main development phase is the React frontend.

The frontend will integrate:

```text
Authentication
Subscription selection
Model selector
Conversation sidebar
Chat messages
Attachment uploader
Image preview
Document display
Usage indicators
Payment history
Billing management
```

---

# License

Add the appropriate project license before publishing the repository.

Example:

```text
MIT License
```

---

# Author

Developed as a multi-provider AI Chat Aggregator platform using Laravel.

---

# Summary

Chat Aggregator provides a unified backend for interacting with multiple AI models while managing:

```text
Users
Subscriptions
Plans
Features
Models
Attachments
Conversations
AI requests
Token usage
Costs
Payments
Billing cycles
```

The system is structured so new AI providers, models, subscription plans, features, and pricing strategies can be added without redesigning the main application architecture.
