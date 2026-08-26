# V121 — VVR Sales Copilot Workspace

## Included

- New authenticated `Armory → VVR Sales Copilot` workspace.
- Dedicated `/armory/sales-copilot` route protected by the existing Armory policy.
- Reserved department, conversation-stage, objection, and context inputs.
- Planned response structure for a recommended response, explanation, follow-up question, listening guidance, warnings, and source links.
- Explicit pre-activation state so the application does not present generic or ungrounded advice before VVR's knowledge is installed.
- Feature tests for authentication, inactive-user denial, route rendering, and Armory navigation.

## Next build

The next milestone should ingest the user's approved sales and negotiation material, define source precedence and department-specific boundaries, then implement the server-side coaching request and response workflow. No AI provider calls or database migrations are included in this workspace-only release.
