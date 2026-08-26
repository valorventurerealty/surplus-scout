# VVR AI CSV country fix v52

This release fixes Surplus CSV staging failures when a country is supplied as `United States`, `USA`, or another value longer than two characters.

- Common US and Canadian country labels are normalized to `US` and `CA`.
- Full US state names are normalized to their two-letter codes.
- The private staging field safely accepts country names up to 100 characters.
- Rows with a different number of columns than the header are shown as invalid with an unquoted-comma warning instead of crashing the complete upload.
- No CRM records are created until the normal review and approval step.

Run the included migration during deployment.
