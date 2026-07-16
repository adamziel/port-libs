# Real Upstream Corpus: UPSERT RETURNING omitted insert column list

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260601T210338Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test` `upsert4-1.1`: `INSERT INTO t1 VALUES(...) ON CONFLICT DO NOTHING` omits the insert target-column list and skips a primary-key conflict.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test` `upsert4-1.3`: omitted insert target columns with a secondary `UNIQUE` conflict update.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test` `upsert4-1.7` and `upsert4-1.8`: omitted insert target columns with row-value `DO UPDATE SET` assignments, including conflict-key changes.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test` `upsert4-6`: `INSERT OR REPLACE` with omitted target columns still lets an explicit `ON CONFLICT` arm decide before replace policy.

Patch:

- Extended `SQLiteUpsertReturningSql::execute()` to pass bounded table-column metadata into the parser.
- Extended `SQLiteUpsertReturningSql::parse()` to accept `INSERT INTO table VALUES(...) ... RETURNING ...` without an explicit target-column list when the executor can infer target column order from the supplied table row image.
- Added `SQLiteRealUpstreamUpsertReturningOmittedColumnListDynamicTest.php` with 1000 deterministic repeated-conflict row-stream cases plus named `upsert4.test` catchall, secondary-conflict, row-value assignment, conflict-key update, `OR REPLACE`, and metadata-guard checks.

Non-overlap:

- This is not another omitted `ON CONFLICT` target matrix: existing `SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php` still uses explicit insert column lists.
- This is not another `OR REPLACE` precedence matrix: existing `SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php` also uses explicit insert column lists.
- This slice owns the parser/executor gap for upstream `INSERT INTO t1 VALUES(...) ON CONFLICT ...` forms with omitted insert target columns.

Focused coverage:

- 1008 new focused TestRunner PASS cases.
- 4024 new focused behavior assertions.
- `lane-status.json` `phpPass` moves `6269881 -> 6270889` (+1008).

Verification:

- Red-first probe before the source change: omitted insert column list failed with `InvalidArgumentException: SQLite UPSERT RETURNING requires a target column list`.
- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php` passed with no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOmittedColumnListDynamicTest.php` passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOmittedColumnListDynamicTest.php` passed `1 test files, 4024 assertions, 0 failures`.
- Adjacent parser check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php` passed `1 test files, 60 assertions, 0 failures`.
- Adjacent no-target corpus check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNoTargetDynamicTest.php` passed `1 test files, 3016 assertions, 0 failures`.
- Adjacent `OR REPLACE` corpus check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningOrReplaceDynamicTest.php` passed `1 test files, 29004 assertions, 0 failures`.
- Adjacent literal SELECT corpus check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningLiteralSelectDynamicTest.php` passed `1 test files, 6008 assertions, 0 failures`.
- No-domain guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed `1 test files, 8 assertions, 0 failures`.
- Status JSON: `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'` reported `lane-status.json valid`.
- Whitespace: `git diff --check -- lanes/libsqlite` passed with no output.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteUpsertReturningSql` executor and infers column order from lane-local row-array table metadata.
