# UTF-16 NOCASE LIKE RTRIM canonical plan

This slice adds the stable `wordpressOptionNameUtf16NocaseRtrimPlan()` entry
point on the existing canonical `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`.
It preserves the accepted numbered observable metadata while giving new callers
a non-numbered production method.

The focused test covers:

- canonical method parity with the existing `wordpressOptionNameNoCasePlan()`
- SQLite `RTRIM` space-only candidate behavior with untrimmed residual `LIKE`
- ASCII-only `NOCASE` behavior for UTF-16LE/UTF-16BE option names
- malformed UTF-16 source-row rejection evidence

Root-gate reproduction attempts on this accepted base did not reproduce the
supervisor-listed failures:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowGroupsRangeCurrentNext18Test.php`
  passed with `1 test files, 46 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext78Test.php ... lanes/libsqlite/tests/SQLiteSuiteEvidenceCurrentNext103Test.php`
  passed with `26 test files, 1043 assertions, 0 failures`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCanonicalPlanTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCanonicalPlanTest.php lanes/libsqlite/tests/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNext156Test.php`
  passed with `2 test files, 96 assertions, 0 failures`.
- UTF-16 NOCASE/RTRIM family check:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCanonicalPlanTest.php ... lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext233Test.php`
  passed with `63 test files, 4792 assertions, 0 failures`.

Dependency closure: no new support component is needed; this reuses the
existing UTF-16 source decoder, RTRIM index-key planning, ASCII NOCASE residual
LIKE handling, and malformed UTF-16 diagnostics.
