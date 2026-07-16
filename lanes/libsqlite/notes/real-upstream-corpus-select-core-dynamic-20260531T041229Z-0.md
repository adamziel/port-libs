# Real upstream SELECT core dynamic select5

- Session: `port-dev-sqlite-yield-dyn-real-select-20260531T041229Z`
- Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`
- Ported upstream scenarios: `select5-1.1`, `select5-1.2`, `select5-2.3`, `select5-3.1`, `select5-4.1` through `select5-4.5`, `select5-6.2`, and `select5-8.1` through `select5-8.8`.

This slice adds `SQLiteRealUpstreamSelectCoreDynamicSelect5Test.php`, a generic application-table dynamic corpus for aggregate `GROUP BY`, aggregate `ORDER BY`, `HAVING`, zero-row aggregate return values, NULL grouping equality, and aggregate join grouping. It is non-overlapping with the accepted/select-adjacent dynamic files for `select3.test`, `select7.test`, and `select8.test`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSelect5Test.php`
- Result: `1 test files, 4410 assertions, 0 failures`
- PASS-line movement: `+1102`
- `lane-status.json` `phpPass`: `2006296 -> 2007398`

Dependency closure: no new support component is needed. The slice reuses the existing `SQLiteSelectSql` row-array SELECT executor and only adds upstream-derived behavior coverage.
