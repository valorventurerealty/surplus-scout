# V59 — Email Merge Fields

This cumulative release makes the existing secure merge-field engine visible and usable from the Email composer.

- Centralized the allowlisted field registry in `config/email.php`.
- Added click-to-insert merge fields to the composer.
- Updated Armory guidance to reflect that live sending now resolves fields.
- Kept missing fields visible and blocked from delivery.
- Retained the exact-message approval fingerprint after CRM values are merged.

No database migration or frontend asset build is required.
