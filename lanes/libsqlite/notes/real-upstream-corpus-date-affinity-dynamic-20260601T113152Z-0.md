# real-upstream-corpus-date-affinity-dynamic-20260601T113152Z-0

Status: ready handoff for an additive real upstream date/affinity dynamic
corpus slice.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/literal.test`
- `literal-3.1` through `literal-3.8`: numeric SQL literals accept one
  underscore only between two digits, including integer, real, exponent, and
  int64 boundary forms.
- `literal-4.0` through `literal-4.16`: malformed separator placement remains
  rejected.

Implementation:

- `SQLiteSelectSql` now accepts digit separators in decimal integer, real, and
  exponent numeric literals, normalizing underscores before the existing
  integer/real value paths.
- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicNumericLiteralUnderscore20260601T113152ZTest.php`
  with 1,134 focused PASS cases and 3,381 assertions. The generated valid
  matrix is checked against the local `sqlite3` oracle; the upstream invalid
  `literal-4.*` forms are asserted as rejected.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicNumericLiteralUnderscore20260601T113152ZTest.php`
  - `1 test files, 3381 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicNumericLiteralUnderscore20260601T113152ZTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php -r '$json = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status.json valid\n";'`
  - `lane-status.json valid`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- Existing accepted coverage owns `hexlit.test` numeric hexadecimal literals,
  quoted hex string affinity, `numcast.test`, `affinity2.test`,
  `affinity3.test`, `types2.test`, `types3.test`, and saturated date4/date5
  rows. This slice isolates `literal.test` numeric underscore parsing and
  malformed underscore rejection.

Dependency closure:

- No new support component is needed. The patch reuses the existing
  parser/executor path, local `sqlite3` oracle checks used by other real
  corpus tests, and the existing scalar `quote()` / `typeof()` execution
  helpers.
