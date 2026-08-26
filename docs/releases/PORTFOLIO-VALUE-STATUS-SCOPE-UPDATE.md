# Portfolio Value status scope update

Portfolio Value now sums `expected_sales_price` only for properties whose status is Owned, Actively Working, Marketing, or Under Contract. Research, Bidding, Sold, and Archived properties remain visible in the appropriate workspaces but do not contribute to Portfolio Value.

The definition is centralized on `PropertyStatus` and is used consistently by both Financials and Pipeline. Other Financials totals retain their existing behavior.
