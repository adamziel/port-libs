# Upstream Suite Current-Source Window Consolidation

## Change

- Replaced the numbered production methods for upstream veryquick current-source windows `next261-276`, `next277-292`, and `next293-308` with one stable `upstreamVeryquickShardCurrentSourceWindow()` helper.
- Migrated the three direct focused tests to pass the slice range, status slug, and countability keys explicitly.
- Left the numbered test filenames and local fixture helper names intact for direct historical coverage; the production numbered wrappers are removed.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext261276Test.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext277292Test.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext293308Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext261276Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext277292Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext293308Test.php`
  - `3 test files, 126 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This is production helper consolidation inside the existing upstream-suite evidence class.
