# real-upstream-corpus-upsert-returning-dynamic-20260601T052622Z-0

Base accepted HEAD: `f21524404044b11f3b8895597ad5fc6ac48001c6`.

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Upstream sections: `upsert1-400` and `upsert1-410`

## Ported Behavior

This slice ports the upstream `PRAGMA count_changes=ON` UPSERT case where a
multi-row INSERT with `ON CONFLICT(a) DO UPDATE SET b=b+1` returns a
`rows inserted` count row of `1`, while the statement still changes four row
images: three update row images and one inserted row image. The final ordered
table image follows upstream `upsert1-410`: `four=1`, `one=3`, `three=2`,
`two=1`.

The implementation is `SQLiteUpsertCountChangesPlan`, which reuses
`SQLiteUpsertDoUpdateWherePlan` for native row application and layers the
upstream count-changes result-row rule on top of the inserted-row count.

## Non-overlap

This does not repeat trigger count-changes behavior from `pragma.test`, UPSERT
conflict arm priority, partial-index target admission, trigger traces, fault
cleanup, JSON planner behavior, or standalone RETURNING projection cases. It
owns only `upsert1.test` `upsert1-400`/`upsert1-410` count-changes UPSERT
result-row behavior and final image verification.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
UPSERT row executor and adds a small count-changes planner for the upstream
result-row distinction.

## Focused Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertCountChangesPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpsertCountChangesPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertCountChangesDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertCountChangesDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertCountChangesDynamicTest.php`
  - `1 test files, 15009 assertions, 0 failures`
  - `1001` focused PASS cases: 1000 dynamic upstream variants plus source citation/dependency closure.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - clean
