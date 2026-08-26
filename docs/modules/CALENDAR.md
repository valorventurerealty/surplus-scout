# Calendar module

Calendar is VVR's operational calendar for meetings, tax deed auctions, and foreclosure opportunities. It provides a month grid and upcoming-event list while retaining an auditable record for each event.

Each event stores its type, date/time, optional VVR property link, notes, creator/updater IDs, timestamps, and soft deletion. Meetings require a title. Auctions require a parcel number, normalized parcel number, HTTPS event URL, property address, and county. Max bid remains optional and private.

Allowed counties are Putnam, Osceola, Marion, Polk, Brevard, and Orange. Duplicate protection prevents the same normalized parcel, auction type, and start time from being scheduled twice.

All active users may view Calendar. Property-management roles may create and update events. Only financial-access roles may view or submit max bids. Only Owner and Admin may archive events. Policies and Form Requests enforce these rules on the backend.

Auction times use `APP_TIMEZONE`, defaulting to `America/New_York`, so Florida auctions render with the correct EST/EDT designation independently of the hosting server timezone.

The Google Calendar integration synchronizes VVR-created events outward and can import future Google-created events as Meetings. Imported meetings are marked Google-managed and read-only in VVR; Google reschedules and cancellations reconcile during the next five-minute run. Each event identifies its source and synchronization state. See `docs/modules/GOOGLE-CALENDAR-INTEGRATION.md`.
