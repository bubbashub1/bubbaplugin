# Missing-information research automation — staging setup

The Listing Checker research worker is implemented, but automatic runs require a hosting cron entry. The admin controls only queue/pause/resume work; they do not execute server commands.

## Configure a staging cron

In the staging hosting control panel's Cron Jobs section, add a command that invokes the research worker from the staging copy of the repository:

```sh
php /home/sites/30b/4/46375b1c19/staging_html/api/listing-checker-research-cron.php
```

Use the hosting cron schedule **every four hours** (for example, minute 17 past 0, 4, 8, 12, 16, and 20 hours). Do not use the live `public_html` path. Confirm the hosting account's actual PHP CLI path if the panel requires an absolute executable path.

## Enable and test

1. Open staging Admin → Listing Checker Settings.
2. Enable missing-information research, save, and ensure the worker is resumed.
3. Use **Run missing-info research next cron** to queue a test.
4. Wait for the next cron invocation; the button does not run immediately.
5. Check Recent runs and the Review Queue for suggestions and source evidence.
6. Pause research again if you are still testing.

## Safety

- The worker is CLI-only and uses an advisory lock.
- Settings, enabled state, pause state, day/time windows, interval, batch, request and runtime limits are respected.
- Suggestions are written to the review queue only; no activity/organiser/venue record is directly edited, published, or contacted.
- Search keywords must not contain the Bubba Hub name.
- Configure this cron against staging only until testing is complete.
