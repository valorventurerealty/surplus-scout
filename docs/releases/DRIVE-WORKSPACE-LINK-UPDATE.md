# Drive workspace link update

The authenticated workspace navigation includes a **Drive** item that opens VVR's shared Google Drive folder in a new browser tab.

The destination is configured through `config/vvr.php` and `VVR_DRIVE_URL`. The supplied VVR folder is the default. The navigation renders the item only when the resolved URL is a valid HTTPS URL. External links use `target="_blank"` with `rel="noopener noreferrer"`.

To replace the destination without changing application code, update the private production `.env`:

```dotenv
VVR_DRIVE_URL="https://drive.google.com/drive/folders/your-folder-id"
```

Then run `php artisan optimize:clear` and `php artisan optimize`.
