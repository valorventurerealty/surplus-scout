# V117 Email Save Compatibility Hotfix

V117 supports both the new dedicated plain-POST save URLs and the legacy email form URLs that may remain in compiled Blade or browser caches during deployment.

It also removes the remaining hidden `PUT` override from the draft editor. New and edited drafts now submit as ordinary POST requests.

After extracting the release over the application, clear and rebuild Laravel caches:

```bash
cd /home/valoljta/vvr-command-center
php artisan optimize:clear
php artisan view:cache
php artisan optimize
```

No database migration is required.
