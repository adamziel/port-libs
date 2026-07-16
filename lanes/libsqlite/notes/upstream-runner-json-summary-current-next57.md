# upstream-runner-json-summary-current-next57

## Behavior

`SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactRecord()` now admits compact
machine-readable bounded-runner summaries in addition to the existing markdown
`Parsed summary` and stdout text paths. The supported forms are:

- `- Summary JSON: \`{...}\``
- fenced `json` blocks
- `RUNNER_SUMMARY_JSON={...}` log lines

The JSON path fills only missing artifact fields: exit code, elapsed seconds,
tests, errors, runner time, jobs, timeout, testset, and selected patterns.
Existing accepted-HEAD provenance, SQLite manifest UUID/commit/version,
active-runner, and zero-error gates are unchanged.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerJsonSummaryCurrentNext57Test.php`
- Result: `1 test files, 43 assertions, 0 failures`
- PASS-line delta: `42`

## Dashboard Delta

- `lane-status.json` `phpPass`: `20008 -> 20050`
- `benchmarkDenominator.mapped`: unchanged at `462 / 1589`; this is a
  runner-countability admission fix, not a newly mapped SQLite behavior unit.

## Non-Overlap

This avoids the queued and accepted release-suite burnup/gap/status ledgers,
artifact directory hydration, focused-runner artifact admission, pgrep
self-probe filtering, and batch50 release-runner denominator burnup. It removes
a narrower current admission blocker: zero-error accepted-HEAD artifacts with
JSON-only bounded summaries were not countable when markdown/stdout summaries
were absent or rotated.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local bounded
runner artifact parser and PHP `json_decode()` only; it does not inspect
secrets, mutate upstream caches, or launch upstream/full-suite runners.
