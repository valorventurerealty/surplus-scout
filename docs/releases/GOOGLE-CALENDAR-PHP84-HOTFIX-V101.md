# V101 — Google Calendar PHP 8.4 Hotfix

The first inbound Google Calendar job exposed a PHP 8.4 callable-arity incompatibility. Laravel collection filters provide a value and key, while PHP 8.4's `is_array()` accepts exactly one argument.

V101 uses explicit single-value closures for Google event and attendee filtering. This is a code-only hotfix with no database migration or frontend build change.
