# Jira Hours (Laravel)

A small local dashboard that shows how many hours you logged in Jira for each day of a week,
against a full-time target of **8 hours per weekday** and **40 hours per week**.

It answers two questions at a glance:

- Did I log a full week?
- For the current week: am I on track right now?

## Branches

| Branch | Stack |
| --- | --- |
| [`main`](../../tree/main) | Plain PHP 8.1+, no framework. Composer is used only for autoloading. |
| `laravel-port` (this one) | The same app on Laravel 13, with feature tests that use a fake Jira. |

Both branches serve the same frontend and return the same JSON. The domain classes
(`Week`, `Worklog`, `WorklogReport`, …) moved from `main` with only the namespace changed.

## Features

- Week view, Monday to Sunday, with previous / next / this week navigation (also the ← and → keys).
- Each day is coloured by status: complete (≥ 8h), partial (4–8h), short (< 4h), in progress (today), upcoming.
- A week progress bar to 40h with an "expected so far" marker.
- Summary cards: total logged, expected so far, difference, average per logged weekday.
- Click a day to see its worklogs, with links to the Jira issues.
- A per-issue table with each issue's share of the week.
- Light and dark themes, keyboard friendly, works on a phone screen.

## Requirements

- PHP 8.3 or newer
- Composer
- Jira Data Center or Server with REST API v2 and a
  [Personal Access Token](https://confluence.atlassian.com/enterprise/using-personal-access-tokens-1026032365.html)
  (tested on Jira Data Center 10.4)

No database and no Node.js are needed.

## Setup

```bash
git clone -b laravel-port <this repository> jira-hours
cd jira-hours
composer run setup     # composer install, creates .env, generates APP_KEY
```

Edit `.env`:

| Key | Example | Meaning |
| --- | --- | --- |
| `JIRA_BASE_URL` | `https://jira.example.com` | Jira address. A trailing slash is removed |
| `JIRA_TOKEN` | `NjQ3…` | Your Personal Access Token |
| `JIRA_TIMEZONE` | `Europe/Kyiv` | Time zone used to split worklogs into days |
| `JIRA_VERIFY_TLS` | `true` | Check the Jira TLS certificate. Use `false` only for a self-signed certificate |

`.env` is in `.gitignore`. Never commit it.
If `JIRA_BASE_URL` or `JIRA_TOKEN` is missing, the API answers 500 and the reason is written to
`storage/logs/laravel.log`.

## Run

```bash
composer run dev       # php artisan serve
```

Open http://127.0.0.1:8000.

## Tests

```bash
composer test
```

The tests never call a real Jira. `Http::fake()` answers every request and `Http::preventStrayRequests()`
fails a test that tries to reach the network. `phpunit.xml` sets its own `JIRA_*` values, so the tests
do not read your `.env`.

| Suite | What it covers |
| --- | --- |
| `tests/Feature/WeekReportTest.php` | The full HTTP flow: the report, the Kyiv day boundary, filtering out other people's worklogs, pagination, every error status |
| `tests/Unit/WorklogReportSummarizeTest.php` | The pure `summarize()` function: week bounds, sorting, broken Jira data |

## API

```
GET /api/week?start=YYYY-MM-DD
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
| 404 | Unknown `/api/*` route |
| 405 | Method other than `GET` / `HEAD`. The `Allow` header lists the allowed methods |
| 500 | Missing configuration or a bug. Details go to the log, not to the response |
| 502 | Jira is unreachable, returned an error, or returned invalid JSON |
| 504 | Jira did not answer in time |

## How it works

```
browser ──GET /api/week?start=…──► WeekReportController
                                     │
                                     ├─ Week::fromInput()          validate the date, find Monday..Sunday
                                     ├─ GET /rest/api/2/search     issues with my worklogs (JQL, paginated)
                                     ├─ GET /rest/api/2/myself     my user key
                                     ├─ GET /rest/api/2/issue/{key}/worklog   for each issue (paginated)
                                     └─ WorklogReport::summarize() pure function: filter, convert time zone, sum
```

A few decisions worth knowing:

- **Search range is one day wider on each side.** JQL `worklogDate` uses the time zone of the Jira user profile,
  which can differ from `JIRA_TIMEZONE`. The extra days are fetched and then dropped after the
  conversion to the configured time zone.
- **Worklogs are filtered by `author.key`**, not by name or display name. The key does not change when a user is renamed.
- **One request per issue (N+1).** Jira has no endpoint for "my worklogs in a date range" with full details,
  so a week with 10 issues makes about 12 requests (around 2 seconds).
- **`summarize()` has no I/O.** All the logic that can be wrong (days, time zones, totals, sorting) is in one
  pure function that is unit-tested without Jira.

## Where things live

| Concern | Plain PHP (`main`) | Laravel (this branch) |
| --- | --- | --- |
| Configuration | `config.php` | `.env` → `config/jira.php` |
| HTTP client | `JiraClient` on curl | `JiraClient` on `Http::` (Guzzle 8) |
| Wiring | `new JiraClient(...)` in `api.php` | `JiraClient` singleton in `AppServiceProvider`, the rest is autowired |
| Routing, 405 | `api.php` checks the method | `routes/api.php` |
| Errors → status | `try / catch` in `api.php` | `withExceptions()` in `bootstrap/app.php` |
| Tests | — | PHPUnit with `Http::fake()` |

```
app/
  Http/Controllers/WeekReportController.php   input → Week → report → JSON
  Jira/                                        domain and Jira client, no Laravel inside except JiraClient
  Providers/AppServiceProvider.php            JiraClient binding, fails fast without URL or token
bootstrap/app.php                              routes and the error contract
config/jira.php                                JIRA_* settings
public/index.html                              frontend: one file, plain HTML, CSS and JavaScript
routes/api.php, routes/web.php
tests/
```

## Security notes

- The token lives only in `.env`. `JiraClient` marks it `#[\SensitiveParameter]`, so it does not appear in stack traces.
- API errors return a short message only. Stack traces go to the log, also when `APP_DEBUG=true`.
- `php artisan config:show jira` prints the token in plain text. Do not paste its output anywhere.
- The `start` value is validated before it is used in JQL, so it cannot change the query.
- Data from Jira (summaries, comments) is rendered with `textContent`, never as HTML.
- The server is meant to run on `127.0.0.1` only. It has no authentication of its own.

## License

[MIT](LICENSE)
