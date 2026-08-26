# V93 — Command Center Navigation Reorganization

## Scope

The shared Command Center sidebar is now organized around daily operating flow rather than a single flat workspace list.

## Navigation order

1. **Daily Command:** Dashboard, Tasks, Calendar, Pipeline
2. **Communication:** Contacts, Phone Calls, Email, Mailers ↗
3. **Revenue / Operations:** Surplus, PreTax Auctions, Properties, Deals
4. **Management / Tools:** Financials, VVR AI, SOPs, Armory, Drive ↗

## Preserved behavior

- Existing named routes and active-state matching are unchanged.
- Surplus, PreTax Auctions, Financials, and VVR AI remain permission-aware.
- Mailers and Drive remain HTTPS-only external destinations that open in a new tab with safe link attributes.
- Restricted roles see the same group structure with only their authorized items.

## Validation

`WorkspaceNavigationTest` verifies the full Owner ordering, security attributes for external links, invalid-URL rejection, and department permission behavior.
