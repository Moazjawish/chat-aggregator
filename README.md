# Chat Aggregator

A Laravel-based **AI Chat Aggregator** that allows users to interact with multiple AI providers and AI models through a unified REST API.

The platform provides a centralized architecture for managing AI providers, models, user subscriptions, authentication, and chat requests.

---

## Supported AI Providers

- OpenAI
- Google Gemini
- Anthropic Claude
- DeepSeek

---

## Features

- Multi-provider AI integration
- Support for multiple AI models
- User authentication and authorization
- User subscriptions to AI models
- AI model management
- Provider-based architecture
- Centralized AI service
- Secure API key configuration
- REST API integration
- Bearer Token authentication

---

## Tech Stack

- **PHP 8.4**
- **Laravel 11**
- **MySQL**
- **Guzzle**
- **OpenAI PHP**
- **Google Gemini PHP**
- **REST APIs**

---

# Installation

## 1. Clone the Repository

```bash
git clone https://github.com/Moazjawish/chat-aggregator.git
cd chat-aggregator
```

## 2. Install Dependencies

```bash
composer install
```

## 3. Create the Environment File

```bash
copy .env.example .env
```

For Linux/macOS:

```bash
cp .env.example .env
```

## 4. Generate the Application Key

```bash
php artisan key:generate
```

## 5. Configure the Database

Open the `.env` file and configure your MySQL database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat_aggregator
DB_USERNAME=root
DB_PASSWORD=
```

Make sure the database exists before running the migrations.

## 6. Run Migrations

```bash
php artisan migrate
```

## 7. Seed the Database

```bash
php artisan db:seed
```

The seeder will populate the database with the required initial data, such as AI models and subscriptions.

## 8. Clear the Configuration Cache

```bash
php artisan optimize:clear
```

## 9. Start the Application

```bash
php artisan serve
```

The application will be available at:

```text
http://127.0.0.1:8000
```

---

# Authentication

The API uses **Bearer Token Authentication**.

Before using the chat endpoint, the user must complete the following steps:

```text
Register
   ↓
Login
   ↓
Receive Authentication Token
   ↓
Add Bearer Token to API Requests
   ↓
Send Chat Requests
```

## 1. Register

First, create a user account using the **Register** endpoint.

Example:

```http
POST /api/register
Content-Type: application/json
```

Example request body:

```json
{
    "name": "Moaz",
    "email": "moaz@example.com",
    "password": "password",
    "password_confirmation": "password"
}
```

---

## 2. Login

After registration, use the **Login** endpoint to authenticate the user.

Example:

```http
POST /api/login
Content-Type: application/json
```

Example request body:

```json
{
    "email": "moaz@example.com",
    "password": "password"
}
```

The login response will return an authentication token.

Example:

```json
{
    "message": "Login successful",
    "token": "YOUR_AUTHENTICATION_TOKEN"
}
```

Copy the returned token.

---

## 3. Add the Bearer Token

The authentication token must be included in the `Authorization` header when accessing protected API endpoints.

Use the following format:

```http
Authorization: Bearer YOUR_TOKEN
```

For example:

```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxx
```

> Replace `YOUR_TOKEN` with the token returned by the login request.

---

# API Usage

The API allows authenticated users to send messages to available AI models.

The user must:

1. Register.
2. Login.
3. Get the authentication token from the login response.
4. Add the token as a **Bearer Token**.
5. Send a request to the chat endpoint.
6. Specify the AI model and message.

---

## Chat Request

```http
POST /api/chat
Content-Type: application/json
Authorization: Bearer YOUR_TOKEN
```

Request body:

```json
{
    "model_id": "gpt-5.5",
    "message": "Hello, how are you?"
}
```

Example response:

```json
{
    "message": "Hello! I'm doing well. How can I help you?"
}
```

---

# AI Provider Configuration

The application requires API keys for the AI providers that you want to use.

Add the required API keys to your `.env` file.

## OpenAI

```env
OPENAI_API_KEY=
OPENAI_CA_BUNDLE=
```

## Google Gemini

```env
GEMINI_API_KEY=
GEMINI_CA_BUNDLE=
```

## Anthropic Claude

```env
CLAUDE_API_KEY=
CLAUDE_CA_BUNDLE=
```

## DeepSeek

```env
DEEPSEEK_API_KEY=
DEEPSEEK_CA_BUNDLE=
```

You only need to configure the providers that you intend to use.

After modifying `.env`, clear the Laravel configuration cache:

```bash
php artisan optimize:clear
```

---

# SSL Configuration

On some **Windows/PHP** environments, you may encounter the following error when communicating with an AI provider:

```text
cURL error 60:
SSL peer certificate or SSH remote key was not OK
```

This usually indicates a problem with the CA certificate configuration used by PHP/cURL.

If required, download a valid `cacert.pem` certificate bundle and configure its path in the `.env` file.

Example:

```env
OPENAI_CA_BUNDLE=C:/path/to/cacert.pem
GEMINI_CA_BUNDLE=C:/path/to/cacert.pem
CLAUDE_CA_BUNDLE=C:/path/to/cacert.pem
DEEPSEEK_CA_BUNDLE=C:/path/to/cacert.pem
```

> Use forward slashes `/` in Windows paths to avoid `.env` parsing problems.

Then run:

```bash
php artisan optimize:clear
```

---

# Example API Workflow

### Step 1 — Register

```http
POST /api/register
```

Create a new user account.

### Step 2 — Login

```http
POST /api/login
```

Login using the registered email and password.

### Step 3 — Get Token

The login response returns the authentication token.

```json
{
    "token": "YOUR_TOKEN"
}
```

### Step 4 — Set Bearer Token

Add the token to the request:

```http
Authorization: Bearer YOUR_TOKEN
```

### Step 5 — Send a Chat Message

```http
POST /api/chat
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

```json
{
    "model_id": "gpt-5.5",
    "message": "Hello, how are you?"
}
```

### Step 6 — Receive the AI Response

```json
{
    "message": "Hello! I'm doing well. How can I help you?"
}
```

---

# License

This project is developed for educational and development purposes.
