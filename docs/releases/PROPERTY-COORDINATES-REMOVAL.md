# Property coordinate removal

This cumulative update removes latitude and longitude from the property form, property details, request validation, mass assignment, and document-extraction schema. The original nullable database columns remain in place to avoid a destructive production migration and preserve backward compatibility.

## OpenAI configuration

Property and contact document autofill requires a private OpenAI API key. Add these values only to `/home/valoljta/vvr-command-center/.env`:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=your_private_project_key
OPENAI_EXTRACTION_MODEL=gpt-5.6-terra
OPENAI_REQUEST_TIMEOUT=90
OPENAI_MAX_RETRIES=2
AI_FILE_UPLOAD_LIMIT_KB=10240
PROPERTY_INTAKE_EXPIRATION_HOURS=24
CONTACT_INTAKE_EXPIRATION_HOURS=24
```

Never put the key in `public_html`, frontend JavaScript, Git, screenshots, support tickets, or chat messages.

## Namecheap deployment

1. Back up the MySQL database and private `.env`.
2. Upload and extract the cumulative update into `/home/valoljta/vvr-command-center`, never `public_html`.
3. Confirm the OpenAI settings above exist in the private `.env`.
4. Run:

```bash
cd /home/valoljta/vvr-command-center
php artisan down --retry=60
php artisan migrate --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm install --no-audit --no-fund
npm run build
cp -a public/build/. /home/valoljta/public_html/build/
php artisan optimize:clear
php artisan optimize
php artisan up
```

5. Verify configuration without displaying the secret:

```bash
php artisan tinker --execute='dump(filled(config("ai.api_key")), config("ai.extraction_model"));'
```

The first value must be `true`. Then verify `/up`, `/properties/create`, `/contacts/create`, and extraction with fictional documents.

No Composer dependency or destructive database migration is included. If deployment stops after maintenance mode begins, run `php artisan up` once the application is safe to restore.
