# VVR AI action assistant

## Architecture

VVR AI is a backend-only, permission-controlled action assistant. Gemini proposes structured plans; it never writes to MySQL. Laravel owns authorization, validation, approval, transactions, idempotency, execution, auditing, and result links.

Flow:

`UNDERSTAND → PLAN → VALIDATE → APPROVE → EXECUTE → VERIFY → REPORT`

The existing property-document workflow continues to handle “I bought this property” requests. General prompts use `VvrAiActionPlanner`, `VvrToolRegistry`, `VvrAiActionService`, and `VvrToolExecutor`.

## Available tools

Read-only, risk level 0:

- `get_properties`
- `get_property`
- `search_buyers`
- `analyze_data` for pipeline, overdue-task, and authorized financial summaries
- `search_surplus_cases`
- `get_surplus_case`
- `search_sops`
- `get_sop`

CRM writes, risk level 2 and explicit approval:

- `update_property`
- `change_pipeline_stage`
- `update_marketability_checklist`
- `create_task`
- `create_auction_event`
- `update_surplus_case`

Property creation uses the dedicated extraction and editable review form. Calendar supports manually created meetings, but the general AI calendar tool remains disabled until its separate input and approval contract is implemented.

## Approval and execution

Plans persist in `ai_action_plans`; exact calls persist in `ai_tool_calls`. Before approval the interface displays the intent, summary, missing information, warnings, tool, exact JSON arguments, risk level, and expiration.

At approval Laravel:

1. verifies plan ownership and conversation ownership;
2. verifies expiration and current status;
3. reloads and locks the plan;
4. checks the current user role against the live tool registry;
5. revalidates each argument;
6. reauthorizes each affected CRM record;
7. executes all calls in one database transaction;
8. verifies results and stores direct record links.

Any required failure rolls back prior writes. The plan is marked failed outside the rolled-back transaction and never claims success.

## Idempotency

Every call receives a unique SHA-256 key derived from conversation, sequence, tool name, and canonical arguments. Completed plans return their recorded result on repeated approval and do not execute again.

## Permissions and data minimization

AI access never grants additional CRM authority. Read-only users receive read tools only. Financial fields are omitted from property tool results unless the logged-in user can view property financials. Document uploads remain prohibited for users without source-document access. Permissions are checked during planning, retrieval, review, approval, execution, and result display.

## Prompt-injection boundary

User content and documents are labeled untrusted data. They cannot change registered tools, role permissions, approval requirements, or system rules. Critical actions are read only from structured output and then independently validated by Laravel.

## Shared-hosting behavior

The action planner is synchronous and resumable through persisted plans. It requires no Docker, Redis, Python process, or continuously running worker. Database queues and the existing scheduled cron remain compatible with Namecheap Stellar.

## Known limitations

- No email, SMS, payments, deletion, publication, signatures, filings, scraping, or other level-3 action.
- No AI-created generic calendar item until a dedicated meeting input and approval contract is implemented.
- Buyer search uses contact classifications; advanced buyer-criteria matching requires the future buyer-criteria schema.
- The action engine does not store private model reasoning or chain-of-thought.
