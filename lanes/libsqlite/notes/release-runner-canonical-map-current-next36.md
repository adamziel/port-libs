# Release Runner Canonical Map Current Next36

- Slice: `yield-sqlite-release-runner-upstream-canonical-map-current-next36`
- Behavior: `SQLiteUpstreamSuiteEvidence::releaseRunnerUpstreamCanonicalMapCurrentNext36()` canonicalizes bounded-runner artifact records by current/next accepted HEAD, testset, and normalized pattern set before release/all countability decisions.
- Status delta: `lane-status.json` `phpPass` moves from `12903` to `12959` using the exact 56 PASS lines from the focused test run. `benchmarkDenominator.mapped` is unchanged at `461 / 1589`; this is release-runner provenance/countability plumbing, not a fresh upstream inventory row.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php

php -l lanes/libsqlite/tests/SQLiteReleaseRunnerCanonicalMapCurrentNext36Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteReleaseRunnerCanonicalMapCurrentNext36Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerCanonicalMapCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 316 assertions, 0 failures
```

## Non-Overlap

This avoids accepted guarded countability preflight, focused runner artifact admission, artifact hydration, accepted-head launch mapping, next29 parity ledger, and next30 `.audit` extension scanning. The new behavior is the canonical in-memory map that dedupes mixed current/next/stale artifacts and blocks duplicate next-source launches before any broad runner is started.

## Dependency Closure

No new support component is needed; the slice reuses existing bounded runner artifact records and accepted provenance gates only. No broad `all` or `release` runner was launched.
