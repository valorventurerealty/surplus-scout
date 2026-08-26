# Shared-Hosting Email Template POST Preservation — V89

## Root cause

After V88, clicking Save reached `/armory/email-templates/save` as a GET and Laravel correctly returned 404 because the endpoint is POST-only. This indicates that an absolute form submission was redirected before Laravel handled it, converting the request method on the shared-hosting path.

## Resolution

- The creation form uses the same-origin relative action `/armory/email-templates/save`.
- The Save button independently specifies `formmethod="POST"` and the same relative `formaction`.
- The POST therefore remains on HTTPS and reaches the dedicated controller action without an intermediary scheme redirect.

No migration or frontend asset build is required.
