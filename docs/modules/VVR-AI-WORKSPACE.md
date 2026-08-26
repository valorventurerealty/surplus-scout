# VVR AI workspace

## Purpose

VVR AI is the internal, permission-bound entry point for AI-assisted CRM work. Manual property creation remains available at **Properties > Add Property** and never calls Gemini. AI-assisted property intake begins at **VVR AI**.

## Property-intake workflow

1. The authenticated user writes a prompt such as `I bought this property`.
2. The user may attach a PDF, DOCX, TXT, CSV, JPG, JPEG, or PNG.
3. The prompt and source are stored privately and sent to Gemini from Laravel only after the external-processing acknowledgment is accepted.
4. Gemini returns schema-constrained candidate property facts.
5. Application code normalizes the candidate fields and searches for parcel, address, and document-hash duplicates.
6. VVR AI persists the conversation and displays extracted, missing, and conflicting information.
7. The user edits the normal Laravel property form.
8. The user must explicitly approve the Level 2 CRM write.
9. Laravel rechecks identity, permission, intake ownership, status, expiration, validation, and duplicates.
10. Property creation, checklist initialization, private attachment, conversation completion, and the completion message occur within the property transaction.

## Permissions

All active roles may open VVR AI, but the available tools are filtered by the logged-in user's CRM permissions. Conversations and plans are user-owned. Read Only users receive no write tools. Financial results are removed for users without financial permission. Property document uploads remain restricted to roles allowed to access private source documents.

## Data handling

- The Gemini key is read only from the server `.env`.
- The browser never calls Gemini.
- Uploaded files remain on Laravel's private `local` disk.
- Prompt and document content are retained as workflow evidence.
- Documents are untrusted data and cannot alter permissions, tools, or approval rules.
- Model-extracted values are labeled `extracted`, not `verified`.
- No hidden chain-of-thought is requested or stored.

## Shared-hosting behavior

The initial extraction is synchronous within the configured request timeout, so it works without Redis or a permanently running worker. Conversations and intake state are persisted before and after extraction, allowing failures and approvals to remain auditable. A future milestone may move extraction behind database queues and scheduled queue execution without changing the property tool layer.

## Action workflow

General prompts are classified into strict action plans. Authorized read tools run immediately. Property updates, pipeline movement, checklist changes, tasks, and auction events pause at an exact review screen and require approval. Laravel revalidates permissions and arguments at execution time and runs a multi-action plan transactionally. Buyer/contact search and bounded CRM summaries are read-only.

See [VVR AI actions](VVR-AI-ACTIONS.md) for the tool registry, approval lifecycle, idempotency, audit behavior, and current limitations.
