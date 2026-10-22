=== AR Net Logger ===
Contributors: kamodev
Tags: ham radio, amateur radio, net control, calendar, discord
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule, publish and log Amateur Radio nets: recurring schedules, a public calendar, iCal export, Net Control assignments, online check-ins, Discord and Facebook.

== Description ==

AR Net Logger helps radio clubs, ARES / RACES groups and net managers run their on-air nets from WordPress.

* **Nets**: name, type (directed, traffic / NTS, ARES, SKYWARN, rag chew, ...), frequency, mode, band, repeater / access info, description, calendar color.
* **Net script**: a formatted preamble / script for Net Control, written in the WordPress visual editor, with per-session placeholders ({ncs_callsign}, {frequency}, {topic}, ...) and a sample directed-net script.
* **Schedules**: weekly, every other week, monthly by week of the month (1st–5th or last), one-time, or manual. Days of the week, months, start / end dates, start / end times.
* **Session overrides**: change the date, time, topic, Net Control Station or frequency of any single session, cancel it, or add special sessions.
* **Net pages**: every net at `/net/{net-name}/` and every session at `/net/{net-name}/?p={id}`, with a directory at `/net/`.
* **Calendar shortcodes** with visitor filters (Net, Type, Mode, Band): `[arnl_calendar]`, `[arnl_upcoming]`, `[arnl_nets]`, `[arnl_event]`, `[arnl_checkin]`, `[arnl_net]`.
* **Calendar export**: subscribable iCal feeds (filterable by net, type, mode and band), single-session .ics downloads, Google Calendar and Outlook.com links.
* **Net Log** for the Net Control Station: the net script (formatted, read-only, or in its own window), open / close net, quick check-in entry with a callsign type-ahead that fills in name, QTH and station type from previous check-ins or the US license database (callook.info), short-time, mobile / portable, relays, traffic precedence and count, acknowledgements, QNI / QTC counts that update in the background, CSV export and print.
* **Discord**: each check-in posted to a channel as it is logged (plus "on the air" and closing-summary posts), per-net channels, privacy options.
* **Facebook Page**: upcoming sessions announced automatically (or on demand); cancelled sessions marked; ready-to-paste details for Facebook Page / Group events.
* **Online check-ins** from the website during a configurable window, with callsign validation, duplicate detection, honeypot, rate limiting and optional moderation. The public check-in list shows which stations Net Control has acknowledged on the air and updates itself while the net runs.
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

= How do I change the /net/ address? =

Net Logger → Settings → Net permalink base. It is the slug of the "Nets" page the plugin creates, which must keep the [arnl_net] shortcode.

= How do I hide the calendar filters? =

`[arnl_calendar show_filter="no"]`, or choose which drop-downs to show with `filters="net,type"`.

= How do I post check-ins to Discord? =

Create a webhook in Discord (Server Settings → Integrations → Webhooks), paste its URL under Net Logger → Settings → Discord, save, and send a test message. A net can post to its own channel under Nets → Edit → Sharing.

= Can it create Facebook events or post in a Facebook Group? =

No. Facebook does not allow apps to create events, and it removed posting to Groups in 2024. AR Net Logger posts sessions to a Facebook Page and gives you ready-to-paste event details (Net Log → Share) for creating Page or Group events by hand.

= How does a station know Net Control heard their online check-in? =

When Net Control clicks "Ack" in the Net Log (or ticks "Acknowledged on air"), the station is marked "Acknowledged" in the public check-in list. The list updates itself while the net is running, so there is no need to reload the page.

= What placeholders can a net script use? =

{ncs_callsign}, {ncs_name}, {ncs}, {net}, {type}, {date}, {time}, {end_time}, {timezone}, {frequency}, {topic}, {url} and {site}. They are filled in for each session on the Net Log.

= Can I accept check-ins from non-amateur stations? =

Untick "Require a valid amateur radio callsign format" in the settings.

== Changelog ==

= 1.2.0 =
* Discord: post each check-in to a Discord channel through a webhook as it is logged (NCS or website), plus "net is on the air" and closing-summary posts. Per-net channel or opt-out, name / location / comments options, test message, delivery errors shown in the settings.
* Facebook Page: announce sessions as Page posts automatically N hours before they start or on demand ("Post to Facebook"); cancelled sessions' posts are marked CANCELLED; editable post template; connection test.
* Net Log "Share" box with Discord status, the Facebook post and copy-ready Facebook event details (Facebook does not allow apps to create events or post in Groups).
* Net script: the net preamble is now a formatted script written in the WordPress visual editor (headings, colors, lists), with placeholders filled in for each session ({ncs_callsign}, {frequency}, {topic}, ...) and an "Insert sample script" directed-net script. The Net Log shows it read-only beside the check-in form, with "Open in new window" for a second screen or printing.
* Public check-in list: each station is marked "Acknowledged" by Net Control or "Waiting for Net Control", with counts; the list updates itself while the net runs, and the visitor's own check-in is highlighted.
* New hooks: arnl_checkin_approved, arnl_event_status_changed, arnl_discord_payload, arnl_facebook_message, arnl_facebook_posted, arnl_public_refresh_interval, arnl_script_values, arnl_net_script, arnl_sample_script.

= 1.1.1 =
* Net Log: the check-in list and QNI / QTC counts refresh in the background (AJAX), so website check-ins appear without reloading and without disturbing the check-in form. New rows are highlighted; updates pause while the tab is hidden and can be switched off.

= 1.1.0 =
* Net permalinks: each net at /net/{net-name}/ and each session at /net/{net-name}/?p={id}, with a nets directory at /net/. The base is configurable in the settings.
* Net Log callsign type-ahead: suggestions from previous check-ins and the callook.info license database fill in name, QTH and station type. Also on NCS fields.
* [arnl_calendar] shows Net / Type / Mode / Band filters by default; turn them off with show_filter="no" or choose them with filters="...".
* New [arnl_net] shortcode.

= 1.0.0 =
* Initial release.
