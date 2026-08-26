# Dedicated Armory Email Template Save Route — V88

Production diagnostics showed that submitting a new email template returned to the library without inserting a record, even though the expected resource controller redirects successful creation to the template detail page. V88 removes ambiguity from that write path.

## Resolution

- New templates submit to `POST /armory/email-templates/save`.
- The dedicated route is registered before the resource and dynamic Armory routes.
- The Save button explicitly references the template form by ID.
- Successful creation redirects to the Email Template Library with a confirmation containing the saved template name.
- The existing resource store route remains available for compatibility.

No migration or frontend asset build is required.
