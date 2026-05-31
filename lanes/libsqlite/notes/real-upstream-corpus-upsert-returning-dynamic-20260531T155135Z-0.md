# real-upstream-corpus-upsert-returning-dynamic-20260531T155135Z-0

Base accepted HEAD: `58c47241a5b6db59dbbfb8ad74725a55a4e899e0`

## Source truth

- Upstream cache file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Ported section: `returning1-10.1` through `returning1-10.4`
- Behavior cluster: `RETURNING rowid` against a view with INSTEAD OF INSERT and UPDATE triggers.

This patch adds generic row-array behavior for both upstream capability branches:

- `!allow_rowid_in_view`: INSERT/UPDATE `RETURNING rowid` fail with `no such column: new.rowid`, trigger logs stay empty, and row images remain unchanged.
- `allow_rowid_in_view`: INSERT returns rowid `-1`; UPDATE returns one NULL rowid per matched view row; trigger log order and final row images follow the upstream section.

## Handoff delta

- Added `SQLiteReturningViewRowidPlan` for the upstream returning/view-rowid trigger behavior.
- Added `SQLiteRealUpstreamReturningViewRowidDynamicTest` with 4,006 focused TestRunner PASS cases and 27,011 assertions across 1,000 deterministic row sets.
- Updated `lane-status.json` from `3137763` to `3141769` selected PASS cases. Mapped coverage stays `1589 / 1589`.

## Non-overlap

This does not repeat accepted upsert conflict-target coverage, RETURNING projection carrier assertions, `returning1-11` temp trigger behavior, virtual-table/writable-schema RETURNING guards, trigger9 view-rowid DELETE/UPDATE routing, JSON/VFS/WAL/B-tree clusters, or source-neutral cleanup. The owned behavior is specifically `returning1.test` section 10 view-rowid `RETURNING` semantics through INSTEAD OF INSERT/UPDATE triggers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteReturningViewRowidPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningViewRowidDynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningViewRowidDynamicTest.php` -> `1 test files, 27011 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` -> `lane-status json ok`
- `git diff --check -- lanes/libsqlite` -> pass

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The patch reuses lane-local PHP row-array planning and the read-only hydrated upstream SQLite test cache for source evidence. No example smoke was added because this is generic SQLite upstream corpus coverage, not a user-visible application path.
