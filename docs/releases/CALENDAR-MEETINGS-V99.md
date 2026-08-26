# V99 — Calendar Meetings

## Outcome

Calendar now supports three event types: Tax Deed Auction, Foreclosure Auction, and Meeting. The primary action is **+ Add Event**.

## Meeting records

Meetings require an event title, date, and time. A linked CRM property, location, county, parcel number, HTTPS event link, and notes are optional. Auction-specific fields remain required for auction types, and max bid remains protected by the existing financial permissions.

No missing meeting data is invented or replaced with auction placeholders. The database stores optional auction identifiers as null.

## Google Calendar

Meetings use their event title as the Google Calendar summary. Optional location, link, notes, and linked VVR record information are included only when present. Max bid remains excluded. Synchronization continues through the database queue and existing Namecheap scheduler.

## AI boundary

The existing `create_auction_event` VVR AI tool remains limited to tax deed and foreclosure auctions. Generic AI-created meetings remain disabled pending a separately reviewed tool and approval schema.

## Validation

Feature coverage verifies meeting creation without auction fields, the Meeting selector, the **+ Add Event** action, and meeting-safe Google payload mapping. Existing auction duplicate and permission coverage remains active.
