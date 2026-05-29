# JSON Table Generated Path Rowid Method Consolidation

Consolidated the JSON table generated-path rowid cost planning methods for the
158-178 worker span into descriptive production method/helper names in
`SQLiteJsonTablePlan`.

Direct tests and WordPress examples were renamed to stable unsuffixed filenames
and updated to call the consolidated methods. Runtime array keys and historical
assertion labels are preserved where they are part of the accepted fixture
surface.

Verification:

- `php -l` for changed PHP files: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSourceProfileTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidSeekCostTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSourceTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSourceConstraintTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceOrderTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostConstraintTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceFilterTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceYieldTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidYieldTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCursorTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceBestIndexTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCacheTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceXFilterTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceResumeYieldTest.php`: `14 test files, 822 assertions, 0 failures`.
- Renamed WordPress generated-path rowid examples with `--self-test`: pass.
- `git diff --check -- lanes/libsqlite`: pass.

Dependency closure: no new support component needed; this is a production
method/helper consolidation over existing native JSON table planning behavior.
