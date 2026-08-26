# Armory Email Template Browser Submission Fix — V87

## Root cause

Production diagnostics showed that the database insert was never reached and Laravel logged no request exception. The creation form rendered an empty browser-required category even though the request layer provides a safe `Other` default. Browser-native validation could therefore stop the POST before Laravel received it.

## Resolution

- New forms render **Other** as the selected category.
- Create and edit forms use Laravel validation as the authoritative validation layer.
- Validation errors return to the form in the prominent error summary added in V86.
- The save button carries an explicit `save_email_template` action value.

No migration or frontend asset build is required.
