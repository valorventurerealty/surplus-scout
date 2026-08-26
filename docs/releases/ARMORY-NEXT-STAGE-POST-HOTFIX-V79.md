# Armory Next-Stage Save Hotfix — V79

V79 fixes the 405 response encountered when saving the default next stage from the Interactive Playbook Builder on Namecheap shared hosting.

## Resolution

- The browser form now submits a native CSRF-protected POST request.
- The backend route accepts POST for the shared-hosting form and retains PATCH compatibility for existing callers.
- Authorization, destination-stage validation, destination-step validation, and transactional session behavior are unchanged.
- A regression test verifies that the exact browser POST request persists both the next stage and selected starting step.

No database migration or frontend asset build is required specifically for this hotfix.
