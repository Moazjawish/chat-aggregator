# Chat Aggregator

A Laravel-based AI Chat Aggregator that allows users to interact with multiple AI providers through a unified platform.

## Supported Providers

- OpenAI
- Google Gemini
- Anthropic Claude
- DeepSeek

## Features

- Multi-provider AI integration
- Multiple AI models
- User subscriptions
- Model management
- Provider-based architecture
- Secure API key configuration
- REST API integration

## Tech Stack

- PHP 8.4
- Laravel 11
- MySQL
- Guzzle
- OpenAI PHP
- Google Gemini PHP
- REST APIs

## Installation

1. Clone the repository
   git clone https://github.com/Moazjawish/chat-aggregator.git
   cd chat-aggregator

2. Install dependencies
   composer install

3. Create environment file
   copy .env.example .env

4. Generate application key
   php artisan key:generate

5. Configure database
   Update .env with your MySQL credentials.

6. Run migrations
   php artisan migrate

7. Seed database
   php artisan db:seed

8. Clear configuration cache
   php artisan optimize:clear

9. Start the application
   php artisan serve

The application will be available at:
http://127.0.0.1:8000

## API Usage

The API allows authenticated users to send messages to available AI models.

### Example Request

````http
POST /api/v1/chat
Content-Type: application/json
get bearer token by response of user's login request

Authorization: Bearer YOUR_TOKEN
{
    "model": "gpt-5.5",
    "message": "Hello, how are you?"
}

{
    "message": "Hello! I'm doing well. How can I help you?"
}

## AI Provider Configuration

The application requires API keys for the providers that you want to use.

### OpenAI

```env
OPENAI_API_KEY=
OPENAI_API_BUNDLE=

Gemini
GEMINI_API_KEY=
GEMINI_CA_BUNDLE=
Claude
CLAUDE_API_KEY=
CLAUDE_CA_BUNDLE=
DeepSeek
DEEPSEEK_API_KEY=
DEEPSEEK_CA_BUNDLE=


## SSL Configuration

On some Windows/PHP environments, you may encounter:

```text
cURL error 60:
SSL peer certificate or SSH remote key was not OK

GEMINI_CA_BUNDLE=C:/path/to/cacert.pem
CLAUDE_CA_BUNDLE=C:/path/to/cacert.pem
DEEPSEEK_CA_BUNDLE=C:/path/to/cacert.pem

php artisan optimize:clear

````
