# Identity module

Identity is the security boundary for VVR Command Center. It owns users, role assignment, active status, password credentials, sessions, and password reset tokens.

Accounts are provisioned internally; there is no registration route. Login accepts only active accounts, regenerates the session identifier after authentication, and throttles failures by normalized email plus IP address. Logout invalidates the session and rotates the CSRF token. Password-reset responses do not disclose whether an email exists.

Roles use the `UserRole` backed enum so database values and application behavior cannot silently diverge. Policies translate a role into actions on a particular resource. `Owner` and `Admin` are administrator roles; every active role may view contacts, while `Read Only` cannot mutate them. Future modules must define their own explicit policy matrix rather than putting role checks in controllers or templates.

Production creates the first owner only when `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD` are injected. Production seeding fails closed if they are absent. Remove the bootstrap password from `.env` immediately after successful provisioning.
