# Events integration with My Hub, Planner and newsletter

Depends on approved event feed (#21–23, PR #65), existing My Hub/Planner, newsletter roadmap #29, first-access #24 and recommendations #51.

## Scope
- Add approved events to Calendar and Planner using stable event/occurrence IDs, with no duplicate activity session cards.
- Save event to My Hub favourites and show upcoming registered events separately from suggested events.
- Opt-in town, age range and category preferences for relevant event emails. Do not infer or expose individual children's personal details in outgoing emails.
- Existing server-hosted SMTP/newsletter subscribers remain canonical; no third-party marketing platform introduced.
- Include unsubscribe and notification preference controls; transactional ticket emails remain distinct from marketing.
- Leader-first-access notifications for Bubba Hub events respect configured lead time and explicit eligibility.

## Staging tests
- Event date changes update planner display without losing user notes.
- Deleted/cancelled events show a clear state, not broken links.
- Family/leader permissions and consent are respected.
- Duplicate newsletter sends and event cards are prevented.
- Mobile calendar and Planner Pro work across time zones and DST.
