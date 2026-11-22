# Jira Hours

A small local dashboard that shows how many hours you logged in Jira for each day of a week,
against a full-time target of **8 hours per weekday** and **40 hours per week**.

It answers two questions at a glance:

- Did I log a full week?
- For the current week: am I on track right now?

## Branches

| Branch | Stack |
| --- | --- |
| `main` | Plain PHP 8.1+, no framework. Composer is used only for autoloading. |
| [`laravel-port`](../../tree/laravel-port) | The same app on Laravel 13, with feature tests that use a fake Jira. |

Both branches serve the same frontend and the same JSON API.

## Features

- Week view, Monday to Sunday, with previous / next / this week navigation (also the ← and → keys).
- Each day is coloured by status: complete (≥ 8h), partial (4–8h), short (< 4h), in progress (today), upcoming.
- A week progress bar to 40h with an "expected so far" marker.
- Summary cards: total logged, expected so far, difference, average per logged weekday.
- Click a day to see its worklogs, with links to the Jira issues.
- A per-issue table with each issue's share of the week.
- Light and dark themes, keyboard friendly, works on a phone screen.

## Requirements

- PHP 8.1 or newer with the `curl` extension
- Composer
- Jira Data Center or Server with REST API v2 and a
  [Personal Access Token](https://confluence.atlassian.com/enterprise/using-personal-access-tokens-1026032365.html)
  (tested on Jira Data Center 10.4)

## Setup

```bash
git clone <this repository> jira-hours
cd jira-hours
composer install
make config        # creates config.php from config.example.php
```

Edit `config.php`:

| Key | Example | Meaning |
| --- | --- | --- |
| `base_url` | `https://jira.example.com` | Jira address, without a trailing slash |
| `token` | `NjQ3…` | Your Personal Access Token |
| `timezone` | `Europe/Kyiv` | Time zone used to split worklogs into days |
| `verify_tls` | `true` | Check the Jira TLS certificate. Keep it `true` unless you use a self-signed certificate |

`config.php` is in `.gitignore`. Never commit it.

## Run

```bash
make run           # php -S 127.0.0.1:47321 -t public
make open          # opens http://127.0.0.1:47321/ in the browser
```

Other targets: `make lint` (syntax check), `make help`. Override the address with `make run HOST=… PORT=…`.

## API

```
GET /api.php?start=YYYY-MM-DD
```

`start` is any date. The server moves it to the Monday of that week. Without `start` you get the current week.

```json
{
  "weekStart": "2026-11-16",
  "weekEnd": "2026-11-22",
  "weekTotalSeconds": 123300,
  "days": [
    {
      "date": "2026-11-16",
      "weekday": "Mon",
      "totalSeconds": 30600,
      "entries": [
        { "issueKey": "ABC-12", "summary": "Fix payout rounding", "seconds": 7200, "comment": "code review" }
      ]
    }
  ],
  "issues": [
    { "issueKey": "ABC-12", "summary": "Fix payout rounding", "seconds": 7200 }
  ],
  "jiraBaseUrl": "https://jira.example.com"
}
```

- `days` always has 7 items, Monday to Sunday. `entries` are sorted by start time. `comment` can be `null`.
- `issues` holds only issues with time in this week, sorted by time, largest first.
- All durations are integer seconds.

Errors return a status code and `{ "error": "message" }`:

| Status | When |
| --- | --- |
| 400 | `start` is not a valid `YYYY-MM-DD` date |
| 401 | Jira rejected the token (Jira answered 401 or 403) |
| 405 | Method other than `GET` |
| 500 | Bad configuration or a bug. Details go to the PHP error log, not to the response |
| 502 | Jira is unreachable, returned an error, or returned invalid JSON |
| 504 | Jira did not answer in time |

## How it works

```
browser ──GET /api.php?start=…──► api.php
                                    │
                                    ├─ Week::fromInput()          validate the date, find Monday..Sunday
                                    ├─ GET /rest/api/2/search     issues with my worklogs (JQL, paginated)
                                    ├─ GET /rest/api/2/myself     my user key
                                    ├─ GET /rest/api/2/issue/{key}/worklog   for each issue (paginated)
                                    └─ WorklogReport::summarize() pure function: filter, convert time zone, sum
```

A few decisions worth knowing:

- **Search range is one day wider on each side.** JQL `worklogDate` uses the time zone of the Jira user profile,
  which can differ from `timezone` in the config. The extra days are fetched and then dropped after the
  conversion to the configured time zone.
- **Worklogs are filtered by `author.key`**, not by name or display name. The key does not change when a user is renamed.
- **One request per issue (N+1).** Jira has no endpoint for "my worklogs in a date range" with full details,
  so a week with 10 issues makes about 12 requests (around 2 seconds). Ideas to make it faster are below.
- **`summarize()` has no I/O.** All the logic that can be wrong (days, time zones, totals, sorting) is in one
  pure function that can be tested without Jira.

## Project layout

```
public/
  index.html             frontend: one file, plain HTML, CSS and JavaScript
  api.php                HTTP entry point: input, wiring, error → status code
src/
  JiraClient.php         GET requests to Jira with curl, maps failures to JiraException
  Week.php               value object: a valid Monday..Sunday range
  Worklog.php, Issue.php domain objects built from Jira JSON
  WorklogReport.php      fetches issues and worklogs, builds the report
  JiraException.php      Jira failure with the HTTP status to return
  InvalidInputException.php
config.example.php       template for config.php
Makefile
```

## Security notes

- The token lives only in `config.php` and is marked `#[\SensitiveParameter]`, so it does not appear in stack traces.
- `display_errors` is off in `api.php`. Unexpected errors return a generic message and are logged on the server.
- The `start` value is validated before it is used in JQL, so it cannot change the query.
- Data from Jira (summaries, comments) is rendered with `textContent`, never as HTML.
- The server is meant to run on `127.0.0.1` only. It has no authentication of its own.

## Ideas

- Cache past weeks on the server. They rarely change, and a cache would make a multi-week trend view cheap.
- Fetch worklogs in parallel with `curl_multi` to cut the N+1 latency.

## License

[MIT](LICENSE)
