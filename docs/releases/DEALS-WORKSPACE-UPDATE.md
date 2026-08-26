# Deals workspace update

This release activates the Deals navigation item and adds:

- acquisition, disposition, surplus-recovery, and rental transaction records;
- searchable and filterable deal register with operational counts;
- linked properties, primary contacts, assigned users, and role-based deal parties;
- contract, due-diligence, projected-close, and actual-close dates;
- permission-protected offer, contract, earnest-money, and revenue data;
- private HTTPS document-folder links;
- deal-specific task assignment and task filtering;
- linked-deal sections on property and contact records;
- UUID URLs, generated deal numbers, audit fields, soft deletion, factories, seeders, policies, validation, and feature coverage.

The database migration is `2026_08_22_000024_create_deals_tables.php`. It creates `deals` and `contact_deal`.
