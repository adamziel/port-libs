# real-upstream-corpus-upsert-returning-dynamic-20260531T013942Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.

Added a focused upstream-backed UPSERT/RETURNING partial-index predicate batch:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`.
- Owned upstream sections: `upsert4.test` partial-index target analysis cases `4.1.1`, `4.1.4`, `4.2.1`, `4.2.2`, and `4.2.4`.
- New PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPartialPredicateBatchTest.php`.
- Focused movement: `8002` PASS lines, `26002` assertions, `0` failures.

Non-overlap:

- This slice intentionally avoids the already-present partial-index dynamic batch rows for `upsert4-4.1.2`, `4.1.3`, `4.1.5`, and `4.2.3`.
- It does not touch accepted generalized multi-arm `upsert5`, target-first `upsert1`, excluded-alias `upsert4-8`, or redundant-conflict integrity coverage.
- It reuses existing native UPSERT conflict-arm and partial-index RETURNING executors; no metadata-only admission records or fabricated `.test` script ids were added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPartialPredicateBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPartialPredicateBatchTest.php`
- Pending final handoff checks: no-domain API guard and `git diff --check -- lanes/libsqlite`.

Dependency closure:

- No new support component needed. The batch reuses `SQLiteUpsertDoUpdateWherePlan` and `SQLiteUpsertReturningDynamicPlan`.
