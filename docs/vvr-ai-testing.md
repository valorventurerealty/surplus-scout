# VVR AI testing

Standard tests never call Gemini. `VvrAiActionAssistantTest` injects deterministic structured provider responses and covers:

- write plan creation without execution;
- approval and verified task creation;
- rejection without writes;
- read-only search with financial-field masking;
- approval expiration;
- transactional rollback after a later tool failure;
- repeated approval idempotency;
- tool allowlist enforcement;
- usage and AI audit records.

The existing provider unit test uses Laravel HTTP fakes and verifies untrusted-document boundaries and API-key handling. Property intake tests mock extraction output.

Production smoke checks should use fictional records only. Confirm migration status, open VVR AI, run a read-only property search, prepare a test task, inspect the exact plan, reject it, then repeat and approve it.
