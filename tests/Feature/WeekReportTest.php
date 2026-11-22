<?php

declare(strict_types=1);

namespace Tests\Feature;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class WeekReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_returns_the_week_report_for_the_current_user(): void
    {
        $this->fakeJira([
            'CRASH-12' => [
                $this->worklog('me', '2026-11-15T22:30:00.000+0000', 1800, null),
                $this->worklog('me', '2026-11-18T10:00:00.000+0200', 7200, 'review'),
                $this->worklog('someone-else', '2026-11-18T11:00:00.000+0200', 3600, null),
            ],
        ]);

        $response = $this->getJson('/api/week?start=2026-11-18');

        $response->assertOk()
            ->assertJsonPath('weekStart', '2026-11-16')
            ->assertJsonPath('weekEnd', '2026-11-22')
            ->assertJsonPath('weekTotalSeconds', 9000)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.date', '2026-11-16')
            ->assertJsonPath('days.0.totalSeconds', 1800)
            ->assertJsonPath('days.2.totalSeconds', 7200)
            ->assertJsonPath('days.2.entries.0.comment', 'review')
            ->assertJsonPath('issues', [['issueKey' => 'CRASH-12', 'summary' => 'Fix multiplier', 'seconds' => 9000]])
            ->assertJsonPath('jiraBaseUrl', 'https://jira.test');

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_it_reads_every_worklog_page(): void
    {
        Http::fake([
            'jira.test/rest/api/2/search*' => Http::response(['total' => 1, 'issues' => [['key' => 'CRASH-12', 'fields' => ['summary' => 'Fix multiplier']]]]),
            'jira.test/rest/api/2/myself' => Http::response(['key' => 'me']),
            'jira.test/rest/api/2/issue/CRASH-12/worklog*' => Http::sequence()
                ->push(['total' => 2, 'worklogs' => [$this->worklog('me', '2026-11-17T10:00:00.000+0200', 3600, null)]])
                ->push(['total' => 2, 'worklogs' => [$this->worklog('me', '2026-11-19T10:00:00.000+0200', 3600, null)]]),
        ]);

        $this->getJson('/api/week?start=2026-11-18')
            ->assertOk()
            ->assertJsonPath('weekTotalSeconds', 7200);
    }

    public function test_an_empty_week_has_seven_empty_days(): void
    {
        Http::fake(['jira.test/rest/api/2/search*' => Http::response(['total' => 0, 'issues' => []])]);

        $this->getJson('/api/week?start=2026-11-18')
            ->assertOk()
            ->assertJsonPath('weekTotalSeconds', 0)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('issues', []);
    }

    #[DataProvider('invalidStarts')]
    public function test_an_invalid_start_returns_400(string $query): void
    {
        $this->getJson('/api/week?'.$query)
            ->assertStatus(400)
            ->assertExactJson(['error' => 'start must be a date in YYYY-MM-DD format']);

        Http::assertNothingSent();
    }

    public static function invalidStarts(): array
    {
        return [
            'not a date' => ['start=bad'],
            'impossible date' => ['start=2026-02-30'],
            'array' => ['start[]=2026-11-18'],
        ];
    }

    public function test_a_rejected_token_returns_401(): void
    {
        Http::fake(['*' => Http::response([], 403)]);

        $this->getJson('/api/week')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'invalid Jira access token']);
    }

    public function test_a_jira_server_error_returns_502(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson('/api/week')
            ->assertStatus(502)
            ->assertExactJson(['error' => 'Jira returned 500']);
    }

    public function test_a_jira_timeout_returns_504(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'timed out',
            0,
            new ConnectTimeoutException('timed out', new PsrRequest('GET', 'https://jira.test')),
        ));

        $this->getJson('/api/week')->assertStatus(504);
    }

    public function test_a_missing_token_returns_500_without_details(): void
    {
        config(['jira.token' => null]);

        $this->getJson('/api/week')
            ->assertStatus(500)
            ->assertExactJson(['error' => 'something went wrong']);

        Http::assertNothingSent();
    }

    public function test_a_wrong_method_returns_405_with_allow_header(): void
    {
        $this->postJson('/api/week')
            ->assertStatus(405)
            ->assertHeader('Allow', 'GET, HEAD')
            ->assertJsonStructure(['error']);
    }

    public function test_the_home_page_serves_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_an_unknown_web_page_stays_an_html_404(): void
    {
        $response = $this->get('/no-such-page');

        $response->assertNotFound();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    /** @param array<string, list<array<string, mixed>>> $worklogsByIssue */
    private function fakeJira(array $worklogsByIssue): void
    {
        $issues = array_map(
            fn (string $key) => ['key' => $key, 'fields' => ['summary' => 'Fix multiplier']],
            array_keys($worklogsByIssue),
        );

        $fakes = [
            'jira.test/rest/api/2/search*' => Http::response(['total' => count($issues), 'issues' => $issues]),
            'jira.test/rest/api/2/myself' => Http::response(['key' => 'me']),
        ];
        foreach ($worklogsByIssue as $key => $worklogs) {
            $fakes["jira.test/rest/api/2/issue/{$key}/worklog*"] = Http::response(['total' => count($worklogs), 'worklogs' => $worklogs]);
        }

        Http::fake($fakes);
    }

    /** @return array<string, mixed> */
    private function worklog(string $authorKey, string $started, int $seconds, ?string $comment): array
    {
        return array_filter([
            'author' => ['key' => $authorKey],
            'started' => $started,
            'timeSpentSeconds' => $seconds,
            'comment' => $comment,
        ], fn ($value) => $value !== null);
    }
}
