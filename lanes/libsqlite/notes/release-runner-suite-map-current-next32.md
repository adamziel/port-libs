# Release Runner Suite Map Current Next32

This slice now uses `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteMap()`, a canonical current-to-next release-runner gate that composes existing accepted-head artifact provenance with the full command manifest, selected-script inventory, wildcard expansion, permutation-suite map, and duplicate broad-runner process gate.

It is intentionally a runner/countability blocker-removal surface: it does not launch the upstream Tcl suite, mutate the hydrated upstream cache, or claim a new mapped upstream inventory unit. It prevents counting a next-source release/all runner until the current accepted artifact is preserved, the next artifact is not already present, every suite-map gate is explicit, and no duplicate broad runner is active.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php` => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteMapCurrentNext32Test.php` => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteMapCurrentNext32Test.php` => `1 test files, 317 assertions, 0 failures`.

Status delta:

- `phpPass`: `11206 -> 11261` from 55 newly verified focused PASS lines in `SQLiteReleaseRunnerSuiteMapCurrentNext32Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this is release-runner countability gating, not a newly mapped upstream inventory row.

Non-overlap:

This avoids accepted batch23 and later behavior clusters including ATTACH/temp/VFS open planning, expression evidence matrix, partial-index implication planning, B-tree index delete rebalance, WAL append persistence, PRAGMA metadata, guarded runner countability preflight, UPSERT trigger/FK yield behavior, aggregate ORDER BY cursors, derived-table materialization, JSON table cursor/source/constraint work, VFS writer/lock/sync/rollback paths, WAL checkpoint/savepoint byte truncation, B-tree page moves/root collapse/overflow release, Unicode GLOB, SELECT subqueries, comma LIMIT, GROUP BY/HAVING text, and expression ORDER BY.

Dependency closure:

No new support component is needed. The helper reuses lane-local manifest data, caller-supplied artifact directories, process snapshots, and existing upstream-runner gate helpers only.

Consolidation update:

- Renamed the production helper to `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteMap()` and migrated the direct test calls.
- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php && php -l lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteMapCurrentNext32Test.php` => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteMapCurrentNext32Test.php` => `1 test files, 317 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunner*Test.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunner*Test.php` => `32 test files, 16391 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` => clean.
