## real-upstream-corpus-pragma-schema-dynamic page/application

Base accepted HEAD: `a17218f2cb8d9470c5635d8abf1711981a8d7bfc`.

Added a focused PRAGMA/schema dynamic corpus backed by hydrated upstream SQLite:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-8.3.1` through `pragma-8.3.2`: `application_id` query and parenthesized assignment.
  - `pragma-14.1` through `pragma-14.6`: `page_count` over main, temp, attached, and uppercase schema-qualified forms.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - `pager1-6.4` through `pager1-6.12`: `max_page_count` lower-bound clamping against current page count and larger limit assignment.

Implementation:

- Extended `SQLitePragmaEncodingPageTempStoreState` with `max_page_count` and `application_id` state.
- Preserved existing `page_count` read-only behavior and expanded the parser to accept signed numeric PRAGMA values where required.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicPageApplicationTest.php` with 1,000 generated real-behavior cases plus parse/source-section checks.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaEncodingPageTempStoreState.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicPageApplicationTest.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicPageApplicationTest.php`
  - `1 test files, 5260 assertions, 0 failures`
  - `1003` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaEncodingPageTempStoreCorpusTest.php`
  - `1 test files, 68 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

No new support component is needed. The existing bounded PRAGMA state helper is reused.
