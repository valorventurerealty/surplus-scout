# V112 Email Compose Form Binding Hotfix

## Outcome

The Save draft button is explicitly associated with the email compose form. This prevents the browser from treating the control as workspace navigation when the form is rendered inside the application layout.

## Changes

- Assigned the stable `email-compose-form` ID to both create and edit forms.
- Bound the Save draft submit button to that form with the HTML `form` attribute.
- Retained the V111 validation summary and explicit submit behavior.
- No changes were made to sending, approval, authorization, recipient validation, or SMTP delivery.

## Deployment

Extract the update into `/home/valoljta/vvr-command-center`, overwrite the included views, and run:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No migration is required.
