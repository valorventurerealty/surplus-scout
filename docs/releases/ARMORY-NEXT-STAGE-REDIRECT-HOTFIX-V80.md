# Armory Next-Stage Redirect Hotfix — V80

V80 resolves the remaining 405 shown after saving the default next stage.

## Root cause

The production endpoint correctly accepted POST and PATCH, but the controller returned through a referrer-based `back()` redirect. In the affected browser flow, that referrer resolved to the write-only next-stage endpoint. The browser then requested that endpoint with GET, which Laravel correctly rejected with 405.

## Resolution

- Successful saves and validation failures now redirect explicitly to the Interactive Playbook Builder route.
- The write endpoint remains POST/PATCH only and cannot be invoked with GET.
- CSRF protection, authorization, stage validation, and starting-step validation remain unchanged.
- Regression coverage now verifies the exact safe redirect destination.

No database migration or frontend asset build is required for this hotfix.
