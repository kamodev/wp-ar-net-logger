# AR Net Logger

A WordPress plugin for scheduling, publishing and logging **Amateur Radio (ham radio) nets**: recurring net schedules, per-session overrides, a public calendar with shortcodes, iCalendar export, Net Control Station (NCS) assignments, and check-ins logged both by the NCS in the admin and by stations online from the website.

- **Requires:** WordPress 6.0+, PHP 7.4+
- **License:** GPL-2.0-or-later
- **Download:** [`dist/ar-net-logger-1.0.0.zip`](dist/ar-net-logger-1.0.0.zip), installable from *Plugins → Add New → Upload Plugin*

---

## How it models on-air net operations

The design follows standard net practice: ARRL NTS net procedures, common NCS scripts, and the usual directed vs. open net formats.

| On the air | In the plugin |
|---|---|
| A **net** meets on a regular schedule (e.g. *every Tuesday 7 PM*, *1st & 3rd Thursday*, *every other Saturday*, *Wednesdays June–August*) | **Nets** with a recurrence: weekly, every other week, monthly by week of the month (1st–5th / last), one-time, or manual only. Optional months, start / end dates, start / end times. |
| Each session has a **Net Control Station** and sometimes a **topic** or a changed date or time | **Sessions**: any date can be overridden (date, time, topic, NCS callsign + name, frequency, notes) or **cancelled**, and special one-off sessions can be added. |
| The NCS reads the **preamble**, asks for emergency / priority traffic, then takes check-ins (short-time, mobile / portable, relays, stations with traffic) | The **Net Log** screen shows the net's preamble and an NCS quick reference, plus a fast check-in form (callsign, name, QTH, station type, short time, traffic with precedence & count, relayed by, comments). It fills in name and QTH from a station's previous check-ins. |
| The NCS **opens** and **closes** the net and reports **QNI / QTC** | *Open Net* / *Close Net* buttons; live counts of check-ins (QNI), traffic (QTC), short-time, mobile / portable, relayed and online stations; CSV export and a print view of the log. |
| Listeners want to know **when** and **where** nets meet | Shortcodes for a month calendar, list, upcoming sessions and a net directory; iCal subscribe / download, Google Calendar and Outlook links. |
| Stations that can't be heard well want to be counted | **Online check-in** from the session page, open from *N* minutes before until *M* minutes after the net (or while the NCS has it open), with optional moderation. |

---

## Installation

1. Download `dist/ar-net-logger-1.0.0.zip`, then go to **Plugins → Add New → Upload Plugin** and activate it. (Or clone this repository into `wp-content/plugins/`.)
2. Go to **Net Logger → Nets → Add New Net**: enter the name, frequency / mode / band, days and time, default NCS and the net preamble.
3. Add `[arnl_calendar]` to a page.

On activation the plugin creates three tables (`{prefix}arnl_nets`, `{prefix}arnl_events`, `{prefix}arnl_checkins`), default settings, and a **Net Control Operator** role.

## Admin screens

| Screen | What it does |
|---|---|
| **Net Logger → Schedule** | Upcoming (or past) sessions with filters by net, date range and status. Per session: **Net Log**, **Edit** (override date / time / topic / NCS for that date only), **Cancel / Restore**, **Delete** (reverts a regular session to the net's defaults). Also *Add Special Session* and *Export .ics*. |
| **Net Logger → Calendar** | Month view; click a session to open its Net Log. |
| **Net Logger → Nets** | Nets list (search, active / inactive views, bulk activate / deactivate / delete) and the add / edit form. |
| **Net Log** (from the schedule) | Run the net: open / close, session NCS / topic / private log notes, quick check-in entry, approve / reject / acknowledge / edit / delete check-ins, *Approve all pending*, CSV export, print. |
| **Net Logger → Settings** | Online check-in options, check-in window, public check-in list, flood protection, session details page, iCal feed range, data removal on uninstall, plus a shortcode and feed reference. |

### Roles and capabilities

| Capability | Granted to | Allows |
|---|---|---|
| `arnl_manage_nets` | Administrator, Editor | Create / edit / delete nets and sessions |
| `arnl_log_checkins` | Administrator, Editor, **Net Control Operator** | View the schedule, open / close nets, log and moderate check-ins, export logs |

Settings require `manage_options`.

## Shortcodes

| Shortcode | Output |
|---|---|
| `[arnl_calendar]` | Month calendar with previous / next navigation, color legend, timezone note and subscribe links |
| `[arnl_calendar view="list" days="60"]` | Agenda list instead of a month grid |
| `[arnl_upcoming limit="5"]` | The next sessions, with a **Check in** button while check-in is open |
| `[arnl_nets]` | Directory of nets: schedule, frequency, type, NCS, next session, per-net subscribe links |
| `[arnl_event net="club-net"]` | A net's current / next session with details, add-to-calendar links, check-in form and check-in list |
| `[arnl_checkin net="club-net"]` | Only the online check-in form (and check-in list) for a net's current / next session |

Clicking a session in any listing opens its details, check-in form and check-in list on the same page via `?arnl_event=…`. You can instead pick a dedicated **Session details page** in the settings.

### Filter options

All listing shortcodes accept these filters (comma-separated, combined with AND across attributes):

| Attribute | Values |
|---|---|
| `net` | Net slugs and / or IDs: `net="tuesday-2m,ares-net"` |
| `type` | `directed, open, traffic, emergency, weather, technical, ragchew, training, swap, other` |
| `mode` | `fm, ssb, cw, am, digital, dmr, dstar, ysf, p25, echolink, allstar, irlp, mixed, other` |
| `band` | `160m … 10m, 6m, 2m, 1.25m, 70cm, 33cm, 23cm, multi, internet` |

Display options:

| Attribute | Applies to | Default |
|---|---|---|
| `view` | calendar | `month` (or `list`) |
| `month` | calendar (month view) | current month, `YYYY-MM` |
| `start` / `days` | calendar (list view) | today / `30` |
| `limit` / `days` | upcoming | `5` / `60` |
| `show_filter` | calendar, upcoming | `no`; `yes` adds a "Show: [net]" drop-down for visitors |
| `show_cancelled` | calendar / upcoming | `yes` / `no` |
| `show_ncs`, `show_topic`, `show_time`, `show_frequency` | listings | `yes` |
| `show_legend`, `show_subscribe` | calendar | `yes` |
| `show_description`, `show_next`, `show_ncs`, `show_subscribe` | nets | `yes` |
| `show_log` | checkin | `yes` |
| `nav` | calendar | `yes` (previous / next) |
| `link` | listings | `yes` (link sessions to their details) |
| `title` | calendar, upcoming | — |

Examples:

```text
[arnl_calendar type="emergency,weather" show_filter="yes"]
[arnl_calendar view="list" net="hf-rag-chew" days="90" show_ncs="no"]
[arnl_upcoming band="2m,70cm" limit="3" title="On the air this week"]
[arnl_nets mode="dmr,dstar,ysf"]
```

## Calendar export (iCalendar)

| URL | Content |
|---|---|
| `/?arnl_ics=feed` | Subscribable feed of all active nets (default: 30 days back, 180 ahead) |
| `/?arnl_ics=feed&net=club-net&type=traffic&mode=fm&band=2m` | Same filters as the shortcodes |
| `/?arnl_ics=e12` or `/?arnl_ics=n3-20261021` | Single session download (`.ics`) |
| add `&download=1` | Force a file download instead of inline |

Events use UTC times and stable UIDs, so a rescheduled session updates the original entry in subscribers' calendars instead of duplicating it. Cancelled sessions are published with `STATUS:CANCELLED`. The calendar footer offers *Subscribe (webcal)*, *Google Calendar* and *Download .ics*. Each session page offers *Apple / Outlook (.ics)*, *Google* and *Outlook.com* links.

## Online check-ins

- Open from **15 minutes before** the start to **15 minutes after** the end by default (configurable), or any time the NCS has the net **In Session**. Closed for cancelled and closed sessions and for nets with online check-ins turned off.
- Callsign format validation, with an optional prefix / suffix such as `VE3/W1AW/M`. It can be turned off for GMRS or SWL check-ins.
- Duplicate check-ins of the same station (ignoring `/M`, `/P`, ...) are refused.
- Spam protection: nonce, honeypot field, and per-visitor rate limit.
- Optional moderation: check-ins stay *pending* until Net Control approves them.
- Optional "logged-in users only".
- The public check-in list shows callsign + name, callsign only, or nothing.
- The visitor's callsign, name and QTH are remembered in their browser for next time.

## Data model (CRUD)

| Table | Holds | Create / Read / Update / Delete |
|---|---|---|
| `arnl_nets` | Net definition and recurrence rule | `ARNL_Nets::insert / get / find / query / update / delete` (delete cascades) |
| `arnl_events` | Session rows: overrides of a recurring date (`occurrence_date`), cancellations, special one-off sessions, and any session with check-ins | `ARNL_Events::insert / get / get_by_occurrence / query / update / delete` |
| `arnl_checkins` | Check-ins of a session | `ARNL_Checkins::insert / get / for_event / update / delete` |

Regular sessions are not stored. `ARNL_Schedule::occurrences()` expands each net's recurrence for the requested range and merges in session rows. A session row is created on demand (`ARNL_Schedule::materialize()`) when a session is overridden, cancelled, logged or checked into. The calendar therefore never runs out, needs no cron, and picks up changes to a net's schedule immediately.

### Hooks for developers

Filters: `arnl_net_types`, `arnl_modes`, `arnl_bands`, `arnl_station_types`, `arnl_is_valid_callsign`, `arnl_checkins_open`, `arnl_pre_online_checkin` (return `WP_Error` to reject), `arnl_checkin_prefill`, `arnl_occurrences`, `arnl_describe_schedule`, `arnl_ics_vevent`, `arnl_now`.

Actions: `arnl_net_saved`, `arnl_before_delete_net`, `arnl_event_saved`, `arnl_before_delete_event`, `arnl_checkin_created`, `arnl_checkin_updated`.

## Development

```bash
composer install            # PHPUnit, WordPress Coding Standards
composer test               # unit tests (recurrence engine)
composer lint               # phpcs (WordPress + PHPCompatibilityWP)
bin/build-zip.sh            # builds dist/ar-net-logger-<version>.zip
```

## References

- [ARRL – Chapter Five: NTS Standard Net Procedures](https://www.arrl.org/chapter-five-nts-standard-net-procedures)
- [How to be a Net Control Operator (JPole Antenna)](https://www.jpole-antenna.com/2019/11/06/how-to-be-a-net-control-operator/)
- [Amateur radio net (Wikipedia)](https://en.wikipedia.org/wiki/Amateur_radio_net)
