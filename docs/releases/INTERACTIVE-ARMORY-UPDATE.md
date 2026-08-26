# Interactive Armory update

This release turns Armory scripts into deterministic guided playbooks without requiring OpenAI or another external service.

Authorized Armory managers can configure ordered steps and response branches. Any active authenticated user can start an active script with optional Contact and Property context, follow the guided prompts, choose caller responses, record step notes, resume the session later, and complete or abandon it with an outcome.

All state is server-side. Branch selections are verified against the current step, record access is policy-controlled, writes are transactional, known variables use a strict allowlist, rendered text is escaped, and session events provide a durable execution history.

The release adds four tables: `armory_script_steps`, `armory_script_step_options`, `armory_sessions`, and `armory_session_events`.
