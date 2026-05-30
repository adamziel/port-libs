# Real Upstream Corpus UPSERT RETURNING Dynamic

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T210636Z-0`

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`

Upstream source files:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Ported upstream scenarios:
- `upsert1-100`: `ON CONFLICT DO NOTHING` inserts only the first non-conflicting row and skips later primary-key/unique-key conflicts.
- `upsert1-101`: targeted unique-key `DO NOTHING` preserves the original row and admits non-conflicting rows.
- `upsert1-320`: partial unique-index predicates only arbitrate rows that satisfy the predicate.
- `returning1` `4.2`: `ON CONFLICT DO UPDATE ... RETURNING *` returns the updated row image.
- `returning1` `4.5`: mixed insert/update UPSERT statements return changed rows in statement order.

Implemented behavior:
- Added `SQLiteUpsertReturningDynamicPlan`, a source-neutral row-array executor for dynamic UPSERT/RETURNING behavior.
- Added focused tests with generic `key_name`, `value_text`, and `load_policy` columns.
- Covered conflict targets, alternate unique constraints for catch-all `DO NOTHING`, `excluded.column` assignments, defaults, rowid assignment, NULL conflict-key insertion, partial unique predicates, RETURNING projection order, old-row images, and validation failures.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicPlan.php`: pass
- `php -l lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicTest.php`: pass
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicTest.php`: `1 test files, 42 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`: pass
- `git diff --check -- lanes/libsqlite`: pass

Expected dashboard movement:
- Focused TestRunner growth: `+42` assertions/PASS cases in `SQLiteUpstreamUpsertReturningDynamicTest.php`.
- `lane-status.json` `phpPass`: `718526 -> 718568`.
- Mapped denominator: unchanged, already `1589 / 1589`.

Dependency closure:
- No new support component is needed. This reuses lane-local PHP row-array execution and adds one bounded source-neutral UPSERT/RETURNING behavior helper.

Non-overlap:
- This slice avoids prior accepted UPSERT/RETURNING target/tail/priority/yield/broad metadata clusters and older WordPress-shaped recursive trigger/view UPSERT fixtures.
- It does not add generated fake upstream rows or metadata-only admission records.
