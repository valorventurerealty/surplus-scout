# Armory workspace update

This cumulative release adds the first production Armory increment.

## Included

- Authenticated script library and navigation entry.
- Private PDF, DOC, DOCX, TXT, Markdown, and RTF upload up to 10 MB.
- Pasted/searchable plain-text scripts.
- Categories, Draft/Active/Retired status, and human-managed version labels.
- Search and category/status filters.
- Private authorized downloads with content-sniffing protection.
- SHA-256 duplicate-file detection.
- Metadata editing and recoverable archive behavior.
- Policy, Form Request, service, controller, model, factory, migration, audit coverage, tests, and documentation.
- Clear future boundary for guided interactive scripts.

## Namecheap deployment

Upload the cumulative archive into `/home/valoljta/vvr-command-center`, extract with overwrite enabled, clear Laravel caches, run migrations, rebuild frontend assets, copy the public build, and optimize the application. No Composer dependency is added.
