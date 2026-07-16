# Real Upstream PRAGMA Schema Dynamic Data Version

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T175541Z-0`

Base accepted HEAD: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- Scenarios: `pragma3-300` through `pragma3-340` shared-cache `data_version`, `pragma3-400` through `pragma3-430` WAL observer `data_version`, and `pragma3-510A/B` through `pragma3-520A/B` empty exclusive transactions.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionTest.php`.
- New focused result: `1 test files, 1116 assertions, 0 failures`.
- New TestRunner PASS lines: `141`.
- Mapped denominator change: none claimed; this is additional behavior in an already mapped PRAGMA/schema corpus domain.

Non-overlap:

- Does not repeat existing `pragma.test` schema-query rows, `pragma4.test` table-valued schema argument rows, invalid view table-list coverage, or already accepted schema invalidation tests.
- This specifically targets `pragma3.test` shared-cache/WAL/empty-transaction `data_version` behavior that was not covered by the current `SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php` block ending at `pragma3-190`.

Dependency closure:

- No new support component is needed. The existing `SQLitePragmaSchemaDataVersion` bounded native PHP model is reused.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionTest.php` - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionTest.php` - passed, `1 test files, 1116 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` - passed.
