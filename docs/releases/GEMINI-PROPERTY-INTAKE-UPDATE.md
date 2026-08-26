# Gemini property-intake update

This release adds:

- backend-only Google Gemini configuration;
- provider-independent AI and tool-registry contracts;
- private PDF, DOCX, TXT, CSV, JPG, JPEG, and PNG property intake;
- local TXT/CSV/DOCX conversion and Gemini PDF/image understanding;
- strict structured-output validation and prompt-injection defenses;
- SHA-256 upload reuse and duplicate property warnings;
- sourced extraction review with confidence, missing fields, and warnings;
- explicit approval before transactional Property creation;
- source-document attachment to the created Property;
- permission-filtered VVR tool definitions with every write at approval-required Level 2;
- mocked provider and feature coverage with no live API calls.

No database migration is introduced by this update. It uses the existing `property_intake_files` table created by `2026_08_07_000005_create_property_intake_files_table.php`.
