=== AR Net Logger ===
Contributors: kamodev
Tags: ham radio, amateur radio, net control, calendar, check-in
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule, publish and log Amateur Radio nets: recurring schedules, a public calendar, iCal export, Net Control assignments and online check-ins.

== Description ==

AR Net Logger helps radio clubs, ARES / RACES groups and net managers run their on-air nets from WordPress.

* **Nets**: name, type (directed, traffic / NTS, ARES, SKYWARN, rag chew, ...), frequency, mode, band, repeater / access info, description, net preamble, calendar color.
* **Schedules**: weekly, every other week, monthly by week of the month (1st–5th or last), one-time, or manual. Days of the week, months, start / end dates, start / end times.
* **Session overrides**: change the date, time, topic, Net Control Station or frequency of any single session, cancel it, or add special sessions.
* **Calendar shortcodes** with filters: `[arnl_calendar]`, `[arnl_upcoming]`, `[arnl_nets]`, `[arnl_event]`, `[arnl_checkin]`.
* **Calendar export**: subscribable iCal feeds (filterable by net, type, mode and band), single-session .ics downloads, Google Calendar and Outlook.com links.
* **Net Log** for the Net Control Station: preamble, open / close net, quick check-in entry with name / QTH lookup, short-time, mobile / portable, relays, traffic precedence and count, acknowledgements, QNI / QTC counts, CSV export and print.
* **Online check-ins** from the website during a configurable window, with callsign validation, duplicate detection, honeypot, rate limiting and optional moderation.
* **Net Control Operator** role that can run nets and log check-ins without editing the site.

== Installation ==

1. Upload the plugin zip from Plugins → Add New → Upload Plugin, then activate it.
2. Go to Net Logger → Nets → Add New Net and enter your net's schedule.
3. Add `[arnl_calendar]` to a page.

== Frequently Asked Questions ==

= How do I change one session without changing the whole schedule? =

Net Logger → Schedule → Edit on that session. Changes apply only to that date. Delete the session record to return it to the net's defaults.

= Which filters do the shortcodes support? =

`net` (slugs or IDs), `type`, `mode` and `band`, each comma-separated. Display options include `view="list"`, `show_filter="yes"`, `show_ncs`, `show_topic`, `show_cancelled`, `nav`, `limit` and `days`. The full reference is under Net Logger → Settings.

= Can people subscribe in Google Calendar or Outlook? =

Yes. Every calendar shows Subscribe, Google Calendar and Download .ics links, and each session page has "Add to calendar" links.

= Can I accept check-ins from non-amateur stations? =

Untick "Require a valid amateur radio callsign format" in the settings.

== Changelog ==

= 1.0.0 =
* Initial release.
