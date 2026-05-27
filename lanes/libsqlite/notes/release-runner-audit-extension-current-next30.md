# Release Runner Audit Extension Current Next30

- Slice: `yield-sqlite-release-runner-upstream-burnup-current-next30`
- Accepted worktree base: `ed3ab707dab6c3946d18c40227f85b842f9cd0f8`
- Behavior: guarded SQLite bounded-runner artifact directory scanners now admit both `.md` and `.audit` audit files, infer `.audit` to `.log` sidecars, and feed those records through the existing accepted-HEAD and manifest UUID provenance gates.
- Focused PASS delta: `+56` TestRunner PASS cases in `SQLiteReleaseRunnerAuditExtensionCurrentNext30Test.php`.
- Status delta: `lane-status.json` `phpPass` moved from `10028` to `10084`; mapped upstream denominator is unchanged because this is release/all runner countability plumbing, not a fresh upstream behavior unit.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerAuditExtensionCurrentNext30Test.php
Focused test run: 1 selected test files (root lock skipped)
56 PASS lines, 537 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerAuditExtensionCurrentNext30Test.php lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactDirectoryTest.php lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactHydrationCurrentNext25Test.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
Focused test run: 4 selected test files (root lock skipped)
152 PASS lines, 2151 assertions, 0 failures
```

Non-overlap:

- Avoided accepted batch23/batch25 runner surfaces for generic guarded preflight, artifact hydration, and release-runner directory evidence by narrowing this patch to the unhandled `.audit` extension path in the older countability/provenance directory scanners.
- Did not launch broad `all` or `release` runners and did not claim release parity.

Dependency closure:

- No new support component is needed; the slice reuses existing bounded audit/log parsing, accepted repository HEAD checks, and SQLite manifest UUID gates.
