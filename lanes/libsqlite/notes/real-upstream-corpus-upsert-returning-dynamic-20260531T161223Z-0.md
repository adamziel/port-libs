# real-upstream-corpus-upsert-returning-dynamic-20260531T161223Z-0

Base accepted HEAD: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-6.1.*`: `INSERT OR REPLACE` must run the matching `ON CONFLICT ... DO NOTHING` arm before any replace-side unique-constraint deletion.
  - `upsert4-6.2.*`: `INSERT OR REPLACE` must run the matching `ON CONFLICT ... DO UPDATE` arm before replace processing on a secondary unique constraint.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.2` and `returning1-4.5`: changed INSERT/UPSERT rows are emitted through the `RETURNING` projection stream.

Implemented behavior:

- `SQLiteUpsertReturningSql` now accepts `INSERT OR REPLACE INTO ... ON CONFLICT ... RETURNING` and `REPLACE INTO ... ON CONFLICT ... RETURNING`.
- Matching UPSERT arms are applied before replacement processing, so `DO NOTHING` suppresses the row and `DO UPDATE` mutates the matched current row without deleting another secondary-conflict row first.
- When no UPSERT arm matches, OR REPLACE fallback removes rows conflicting under declared unique constraints, inserts the incoming row, and emits that inserted row through `RETURNING`.
- The executor rejects missing unique-constraint metadata for the OR REPLACE path and preserves the existing unique-target validation for UPSERT arms.

Focused movement:

- New focused file: `lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php`.
- `1002` TestRunner PASS cases.
- `29004` behavior assertions.
- Expected selected `phpPass` movement: `3194686 -> 3195688`.
- Mapped coverage remains `1589 / 1589`; this is additional behavior coverage over already mapped upstream UPSERT/RETURNING inventory.

Red-first check:

- Before the source change, `SQLiteUpsertReturningSql::execute("INSERT OR REPLACE ... ON CONFLICT ... RETURNING", ...)` failed with:
  - `InvalidArgumentException: SQLite UPSERT RETURNING SQL must start with INSERT INTO or INSERT OR IGNORE INTO`

Non-overlap:

- This does not repeat prior `SQLiteRealUpstreamUpsertReturningReplacePrecedenceDynamicTest.php` row-array coverage through `SQLiteUpsertDoUpdateWherePlan`; this slice exercises the SQL text parser/executor path in `SQLiteUpsertReturningSql`.
- This does not repeat OR IGNORE WITHOUT ROWID duplicate suppression, trigger-old-image replay, catchall-only arms, excluded-alias matrices, target-first matrices, or broad upsert5 conflict-arm priority tests.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteUpsertReturningSql` parsing, conflict-arm callbacks, unique-constraint checks, `SQLiteUpsertDoUpdateWherePlan` projection helpers, and generic application row arrays.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php`
  - `1 test files, 29004 assertions, 0 failures`
  - `1002` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningReplacePrecedenceDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrIgnoreWithoutRowidDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php`
  - `4 test files, 43817 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.
