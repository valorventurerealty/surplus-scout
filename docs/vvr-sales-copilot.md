# VVR Sales Copilot

VVR Sales Copilot is a private, authenticated coaching workspace at `/sales-copilot`. It is not a customer-facing chatbot. A user enters the prospect's exact words and receives one short, governed response plus delivery guidance, an objective, listening cues, likely next branches, and the next stage.

## Architecture

`SalesCopilotClassifier` handles safety-critical and canonical intent detection deterministically. `SalesCopilotPlaybookRetriever` ranks active owner-authored and VVR-approved playbooks before lower-priority material. `SalesCopilotEngine` maintains session state, calls the configured structured-output AI provider only when adaptation is useful, validates its result, and falls back to approved deterministic wording on any provider or schema failure. Model output never writes to CRM records.

Sessions and turns preserve prospect statements, concise recommendations, classification, stage, risk flags, playbook matches, provider metadata, token usage, latency, and feedback. Hidden model reasoning is neither requested nor stored.

## Guardrails

- One question maximum in the spoken recommendation.
- Explicit do-not-contact requests end persuasion and set the session next action to `do_not_contact`.
- Legal, probate, entitlement, and statute questions use a fixed escalation bridge and require legal review.
- No government, clerk, or law-firm impersonation; no guarantees; no invented facts, deadlines, credentials, urgency, or competitor claims.
- Session access is limited to its user, except users already authorized to manage Armory.
- Playbook creation and editing require the existing Armory-management permission.
- The configured AI API key stays server-side.

## Source precedence

1. Owner-authored VVR playbooks
2. VVR-approved playbooks
3. Other active playbooks by match and priority
4. Structured provider adaptation
5. Safe deterministic clarification

The nine canonical objection playbooks are installed by `SalesCopilotSeeder`. Training books were not included in this release package, so no book text was ingested or represented as an authoritative source.

## Namecheap deployment

From `/home/valoljta/vvr-command-center`, install the update files and run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=SalesCopilotSeeder --force
export PATH=/opt/alt/alt-nodejs22/root/usr/bin:$PATH
npm run build
php artisan optimize
php artisan route:list --name=sales-copilot
```

No new daemon, Redis service, Node process, or Python service is required. The workspace works without an AI key by using approved deterministic responses. When the configured Gemini provider is available, unmatched context can be adapted with strict JSON output.

## Verification

Run the standard project test command available in the deployed Composer installation:

```bash
./vendor/bin/phpunit --filter=SalesCopilot
```

Verify the canonical objections, fee continuation, legal escalation, DNC behavior, session ownership, playbook management, dark mode, and mobile layout.

## Known limitations

- This milestone coaches and records sessions; it does not automatically update a CRM contact's DNC field because the current contact schema has no confirmed canonical DNC field.
- Practice simulation, transcript ingestion, manager scorecards, and analytics beyond stored feedback remain future increments.
- Responses are plain text and are always escaped by Blade.
