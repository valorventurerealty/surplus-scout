# VVR AI security controls

- Gemini keys remain in the private `.env` and are never returned to Blade or JavaScript.
- All provider traffic originates from Laravel over an HTTPS-only configured endpoint.
- Tool selection is allowlisted by role; model-supplied risk values are ignored.
- All write tools require explicit server-side approval.
- Tool arguments are validated again immediately before execution.
- Policies are applied to the affected model immediately before execution.
- Multi-action writes use a single transaction and roll back on required failure.
- Read results minimize fields and conditionally exclude financial data.
- Uploaded files are private, hashed, size/type constrained, and treated as untrusted.
- AI action and usage metadata are stored without hidden reasoning.
- Level-3, destructive, financial-payment, legal-filing, and external-communication tools are unavailable.
