# Native timetable engine — staging implementation contract

Existing bh_sessions remains canonical for weekly schedule, times, term-time flag and date boundaries. bh_venues links sessions to activities. The new migration is additive and NOT executed.

Before implementation, verify the existing day_of_week numbering and frequency semantics in code and staging data. Interpret occurrence dates and times in Europe/London, including daylight-saving transitions. Respect start/end dates, organiser term calendars and one-off cancellations/reschedules. An absent term calendar must not imply a verified timetable.

Only authorised organisers, assigned volunteers or administrators may confirm a timetable. Record confirmation actor and timestamp. Never equate a scheduled class with available booking places.

The family planner should expand occurrences only for bounded date windows, exclude cancelled occurrences and flag overlaps. Booking URL imports remain optional suggestions; they must not silently overwrite native schedules.

Pre-deployment tests: staging backup and restore, existing schema compatibility, weekday numbering, cross-week reschedules, term boundaries, UK DST, permission isolation, duplicate detection and activity-page compatibility.
