# Source-Neutral Cast/Like/Glob Defaults Dynamic

Micro-slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T222117Z-0`

## Scope

- Neutralized direct cast, LIKE/GLOB, generated-default, and dynamic-trigger fixtures away from historical domain wording.
- Renamed the current-source LIKE/GLOB example to `application-key-name-like-glob-current-source-next88.php`.
- Expanded the source-neutral guard to scan the owned production helpers plus their direct tests/examples for legacy source terms and legacy filenames.
- No `lane-status.json` counter movement: this is a source-neutral cleanup slice, not PASS-line growth.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCastLikeGlobDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteCastCollationLikeCurrentSourceNext123Test.php lanes/libsqlite/tests/SQLiteCastLikeGlobAffinityCurrentSourceNext133Test.php lanes/libsqlite/tests/SQLiteCastRtrimLikeCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteCastRtrimGlobRangeCurrentSourceNext127Test.php lanes/libsqlite/tests/SQLiteLikeGlobCurrentSourceNext88Test.php lanes/libsqlite/tests/SQLiteLikeEscapeGlobCandidateCurrentSourceNext147Test.php lanes/libsqlite/tests/SQLiteInsertDefaultValuesGeneratedDefaultTest.php`
  - `8 test files, 576 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php -l` on changed PHP files passed.
- Updated examples passed their `--self-test` smoke checks.

## Dependency Closure

No new support component is needed. Existing cast, collation, LIKE/GLOB, generated default, dynamic trigger, and row-key cursor helpers are reused with neutral application settings fixtures.
