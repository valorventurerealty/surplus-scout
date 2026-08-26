# Property document Drive link update

## Outcome

Properties now support one optional Google Drive folder URL for relevant documents. Authorized users can paste the HTTPS link while creating or editing a property and open it from the property details screen.

## Security and permissions

- The link must be a valid HTTPS URL and is trimmed before persistence.
- The field is protected by the existing property source-document permission on both input and output.
- Owner, Partner, Acquisition Manager, Disposition Manager, and Admin roles can view and maintain the link.
- Virtual Assistant, Marketing, and Read Only roles cannot view or submit it.
- External links open in a new tab with `noopener noreferrer` protection.
- Google Drive sharing permissions remain the final access control for the files themselves.

## Deployment

Run migration `2026_08_15_000016_add_document_drive_url_to_properties_table.php`, rebuild frontend assets, and refresh Laravel caches using the release deployment procedure.
