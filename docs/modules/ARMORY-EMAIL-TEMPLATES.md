# Armory Email Templates

Armory Email Templates is VVR's governed library of reusable email copy. It is available from the **Email Templates** tab inside Armory.

## Capabilities

- Create, edit, search, filter, preview, copy, and archive templates.
- Organize templates by operational category, including dedicated PreTax Auction Outreach, lifecycle status, and version.
- Store a reusable subject and plain-text message body.
- Use documented merge-field placeholders for future CRM-assisted composition.
- Record create and update activity through the existing audit system.

## Merge fields

Supported placeholders are displayed on the create and edit screens:

- `{{first_name}}`
- `{{last_name}}`
- `{{contact_name}}`
- `{{property_address}}`
- `{{parcel_id}}`
- `{{county}}`
- `{{surplus_amount}}`
- `{{case_number}}`
- `{{sender_name}}`

These are stored as template tokens. This milestone does not replace tokens or send email. A future email workflow must resolve each token from records the user is authorized to view, show a final preview, and require any applicable approval before delivery.

## Security and permissions

- All active authenticated users may read templates.
- Armory managers may create, update, and archive templates.
- Virtual Assistant and Read Only roles cannot mutate templates under the existing Armory permission rules.
- Subjects and bodies are rendered as escaped plain text.
- Archiving uses soft deletion for auditability and recovery.
- No credentials, SMTP settings, or external communications are involved.
