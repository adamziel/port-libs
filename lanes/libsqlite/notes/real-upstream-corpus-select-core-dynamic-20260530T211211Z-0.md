# real-upstream-corpus-select-core-dynamic-20260530T211211Z-0

Base accepted HEAD: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`.

Added focused real-upstream SELECT coverage from hydrated SQLite upstream files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
  - `selectE-1.0`: compound `EXCEPT` keeps binary comparison semantics while `ORDER BY a COLLATE nocase` controls only final ordering.
  - `selectE-2.1` / `selectE-2.2`: projected `COLLATE nocase` on the compound result eliminates case variants before final binary/nocase ordering.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`
  - `selectF-2`: compound `UNION ALL` with `ORDER BY 2, 1` preserves copied source-register values after ordering, including NULL sort position and mixed column names.

The new test file is `SQLiteRealUpstreamSelectESelectFDynamicTest.php` and contributes `1001` distinct TestRunner PASS cases with `4005` behavior assertions. It is non-overlapping with current accepted SELECT files because there was no dedicated `selectE` or `selectF` focused dynamic test, while recent accepted SELECT work covered selectH omit-unused, nested joins, select1/select2/select3/select4/select5/select6/select7/select8/select9/selectA/selectB/selectG, grouped SELECT text, expression ORDER BY, subqueries, JSON table SELECT sources, and related current-source SQL helpers.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectESelectFDynamicTest.php`
  - `1 test files, 4005 assertions, 0 failures`

Dependency closure: no new support component is needed. This reuses the existing `SQLiteSelectSql` compound SELECT executor and upstream hydrated `.test` cache only as source truth for behavior selection.

Root harness: not run - isolated micro-slice.
