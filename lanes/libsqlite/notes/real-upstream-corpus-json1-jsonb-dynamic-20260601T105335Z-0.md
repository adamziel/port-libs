# real-upstream-corpus-json1-jsonb-dynamic-20260601T105335Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`.

Ported upstream sections:

- `json101-5.2`: `SELECT id, json_valid(json), json_type(json), '|' FROM j2 ORDER BY id`.
- `json101-5.2b`: `SELECT id, json_valid(json,5), json_type(json), '|' FROM j2b ORDER BY id`.

Patch content:

- Added `SQLiteRealUpstreamJson101ValidTypeSelectSqlDynamic20260601Test.php`.
- The test builds 1000 dynamic host-row corpora from the upstream JSON object/array shape, runs parser-level `SQLiteSelectSql` over text JSON and JSONB blob columns, and verifies `json_valid`, `json_type`, literal projection, `ORDER BY id`, object predicates, array predicates, and text/JSONB parity.
- Countable growth: 1002 focused TestRunner PASS cases and 10007 focused assertions.
- `lane-status.json` `phpPass` moves from `5792118` to `5793120`; mapped denominator is unchanged.

Non-overlap:

- This slice does not repeat the accepted/queued `json101-5.3` through `json101-5.8` table-valued hidden-source projection work, quoted-path SELECT SQL coverage, value-subtype SELECT SQL coverage, JSON102 tree projection/search coverage, JSON table cursor/source/hidden/visible-constraint work, aggregate/window JSON work, JSON mutation/path/operator coverage, or malformed JSONB diagnostics.
- The selected behavior is specifically parser-level SELECT execution over ordinary host tables that call JSON scalar inspection functions against text JSON and JSONB columns.

Dependency closure:

- No new support component is needed.
- Existing bounded components are reused: `SQLiteSelectSql`, `SQLiteJsonValidity`, `SQLiteJsonInspection`, `SQLiteJsonB`, and `SQLiteJsonCanonical`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValidTypeSelectSqlDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValidTypeSelectSqlDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValidTypeSelectSqlDynamic20260601Test.php`
  - `1 test files, 10007 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Root harness: not run - isolated micro-slice.
