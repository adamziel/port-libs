# real-upstream-corpus-btree-index-dynamic-20260531T160135Z-0

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/where6.test`
- Ported sections: `where6-1.1` through `where6-3.1`
- Behavior cluster: LEFT JOIN ON-clause terms that reference the left table are match guards, not left-row filters. With `i1(c)` present, `ON ... c=1` and `ON ... 1=c` must preserve null-extension and must not probe/filter the left table through `i1(c)`. Equivalent `WHERE c=1` / `WHERE 1=c` terms may filter the left rowset and use `i1(c)`.

## Patch

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where6LeftJoinOnClauseIndexGuardCases()` with 1200 generated real-upstream cases cycling the 20 selected `where6.test` sections.
- Added `SQLiteRealUpstreamBtreeWhere6LeftJoinIndexGuardDynamicTest.php` with per-case assertions for result rows, null-extension counts, ON/WHERE term classification, chosen-index metadata, EXPLAIN equivalence links, invalid corpus size handling, and dependency/non-overlap evidence.
- Updated `lane-status.json` from `3137763` to `3138966` selected PHP PASS lines (`+1203`).

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere6LeftJoinIndexGuardDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere6LeftJoinIndexGuardDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere6LeftJoinIndexGuardDynamicTest.php`
  - `1 test files, 30790 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'`
  - `lane-status.json valid`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Dependency Closure

No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and existing TestRunner harness.

## Non-Overlap

This owns `where6.test` LEFT JOIN ON-clause index-guard behavior only. It avoids accepted `where2`, `where4`, `where7`, `where8`, `where9`, `whereA` through `whereN`, `index*`, `bestindex*`, B-tree page relocation/root-collapse/overflow/freelist release, JSON, WAL, VFS, PRAGMA, SELECT, trigger, UPSERT, and source-neutral cleanup clusters.
