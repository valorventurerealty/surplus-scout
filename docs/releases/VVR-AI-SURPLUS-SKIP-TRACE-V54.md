# VVR AI Surplus skip-trace import v54

VVR AI now understands VVR's 80-column skip-trace CSV format.

The review screen shows each owner and the supplied relatives before approval. During the approved transaction, VVR AI:

- locates the Surplus case by parcel and jurisdiction;
- updates the existing surplus amount and 12% projected fee, or creates a case if none exists;
- enriches blank owner phone, email, and mailing fields without overwriting populated CRM values;
- matches relatives using email, phone, or name plus mailing address;
- creates missing Surplus contacts;
- links each relative to the selected case;
- retains reported relationship, age, alternate phones, emails, and source information in internal notes;
- avoids duplicate cases, contacts, links, and research tasks.

The claimant mailing address remains separate from the property site address. All writes require approval and execute in one database transaction.
