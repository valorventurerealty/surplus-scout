# Valorie website chat

The public `valorventure.us` site hosts a first-party guided assistant named Valorie. It does not use generative AI or third-party tracking. Completed guided conversations are posted server-to-server to:

```text
POST https://valorventure.business/integrations/website-chat
X-VVR-Website-Chat-Secret: <private shared secret>
```

The webhook matches an existing contact by normalized email or phone, or creates a new contact when no match exists. It then creates a high-priority follow-up task, stores the full chat intake, and emails `WEBSITE_CHAT_NOTIFICATION_EMAIL`. Delivery failures are logged without rolling back the contact, task, or conversation.

## Production configuration

Generate one random secret of at least 32 characters. A 64-character hexadecimal value is recommended:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Add it to the Command Center `.env`:

```dotenv
WEBSITE_CHAT_WEBHOOK_SECRET="paste-the-generated-value"
WEBSITE_CHAT_NOTIFICATION_EMAIL="ValorVentureRealty@gmail.com"
```

Add the same value to `/home/valoljta/valorventure.us/config.php` as `chatbot_api_key`. Do not place the secret in browser JavaScript or HTML.

After editing `.env`, run:

```bash
cd /home/valoljta/vvr-command-center
php artisan migrate --force
php artisan optimize:clear
```

SMTP must be configured in the Command Center `.env` for email notifications. The website keeps a local fail-safe record in `storage/chat-submissions.ndjson` and attempts a PHP mail notification if the Command Center cannot be reached.
