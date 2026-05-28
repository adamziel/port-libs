# SQLite suite release-runner current-source next93

## Scope

This patch adds a lane-local countability primitive for guarded SQLite runner
artifact directories that contain mixed source provenance. The new
`SQLiteUpstreamSuiteEvidence::currentSourceNextArtifactDirectoryRecord()` record
separates:

- next-source zero-error artifacts that may be routed onward to focused or
  release countability gates;
- current-source artifacts that preserve the accepted baseline but are not new
  next-source movement;
- stale-source artifacts;
- manifest-mismatched artifacts;
- audit files without paired runner logs.

It does not launch upstream runners and does not claim release parity directly.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
PASS separates current-source and next-source guarded runner artifacts before countability
PASS keeps stale and manifest-mismatched current-source-next artifacts blocked

1 test files, 996 assertions, 0 failures
```

PASS-line delta: `+2` focused PASS cases in
`SQLiteUpstreamSuiteEvidenceTest.php` (`62 -> 64` PASS lines in the focused
run).

## Non-Overlap

This avoids accepted batch68-batch89 suite surfaces for accepted-head artifact
admission, focused runner artifact admission, release blocker closure records,
artifact-set countability, and accepted-head directory provenance. The new
record is narrower: it gates mixed current-source/next-source artifact
directories before promotion to those existing countability records.

## Dependency Closure

No new support component is needed. The patch parses lane-local bounded runner
audit/log artifacts and compares supplied source heads plus the existing SQLite
manifest UUID.

## Next Gate

Use this record when a suite handoff directory contains both current-source
baseline artifacts and next-source attempts. Count only next-source zero-error
artifacts with matching SQLite manifest UUID and paired logs; rerun or repair
stale, failed, manifest-mismatched, active, or missing-log artifacts before
claiming next-source movement.
