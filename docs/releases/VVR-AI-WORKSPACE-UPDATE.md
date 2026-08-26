# VVR AI workspace update

This update moves Gemini-assisted property intake out of the manual property form and into a dedicated VVR AI workspace.

## Included

- VVR AI primary navigation item
- Manual versus AI-assisted choice
- Prompt with optional document upload
- Private, user-owned conversation history
- Structured extraction review
- Missing-information and duplicate warnings
- Editable property proposal
- Explicit Level 2 approval
- Transactional property creation and completion result
- Role and conversation-ownership enforcement

## Deployment

Run migration `2026_08_23_000025_create_vvr_ai_workspace_tables`, rebuild Vite assets, clear caches, and optimize Laravel. Existing Gemini `.env` variables remain unchanged.
