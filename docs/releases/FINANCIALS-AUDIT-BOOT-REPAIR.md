# Financials audit boot repair

The shared `Auditable` model concern now registers the `restored` event only for models that use Laravel's `SoftDeletes` concern.

This resolves the production failure raised while Eloquent initialized `PropertyFinancialSplit`, which is intentionally not soft-deletable:

```text
BadMethodCallException: Call to undefined method App\Models\PropertyFinancialSplit::restored()
```

Soft-deletable Contact, Property, and Task models retain their existing restore audit behavior. Normal audited models retain created, updated, and deleted audit events without attempting to register an unsupported restored event.
