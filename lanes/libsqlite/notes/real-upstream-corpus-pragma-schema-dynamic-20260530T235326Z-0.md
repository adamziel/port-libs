# real-upstream-corpus-pragma-schema-dynamic-20260530T235326Z-0

Base accepted HEAD: `c18695783d58d6f8245967de682828c93b145ece`.

Implemented a real upstream PRAGMA data_version corpus slice from hydrated SQLite upstream source `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`.

Ported upstream sections:

- `pragma3.test` `pragma3-100` through `pragma3-102`: initial `PRAGMA data_version`, `temp.data_version`, and ignored write attempts.
- `pragma3.test` `pragma3-110` through `pragma3-150`: same-connection commits preserve the local value while peer readers observe the external commit.
- `pragma3.test` `pragma3-160` through `pragma3-195`: uncommitted writes are invisible to other connections and data_version values are connection-local.
- `pragma3.test` `pragma3-200` through `pragma3-340`: separate-process and shared-cache writers follow the same external-change detection rule.

Focused behavior:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionConnectionLocalDynamicTest.php`.
- Adds 1,000 dynamic TestRunner behavior cases plus 2 source/dependency evidence cases.
- Uses generic connection names only.
- Non-overlap: this does not repeat the existing `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` coverage for `pragma4.test`, `pragma5.test`, and `schema.test` schema/table-valued PRAGMA shadowing.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionConnectionLocalDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaDataVersionConnectionLocalDynamicTest.php` -> `1 test files, 9506 assertions, 0 failures`.

Expected dashboard movement if accepted:

- `phpPass`: `1238317 -> 1239319` (`+1002` focused PASS lines).
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component needed; this reuses lane-local `SQLitePragmaDataVersionTracker` and connection-generation state.
