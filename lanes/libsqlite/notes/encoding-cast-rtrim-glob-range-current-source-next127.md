# encoding-cast-rtrim-glob-range-current-source-next127

Status: focused PHP behavior growth for CAST expression scans under RTRIM
collation with GLOB prefix ranges.

Behavior:
- Adds `SQLiteCastRtrimGlobRangeCurrentSourceNextPlan` for copied
  `wp_options` current/next scans using `CAST(option_value AS ...)` as the
  range key.
- Models SQLite's two-stage behavior for `GLOB` with an RTRIM index key:
  the RTRIM comparison key can admit space-padded candidates into a prefix
  range, but the binary GLOB residual still decides the final matched rowset.
- Tracks source/schema invalidation, cast-result changes, RTRIM-key changes,
  candidate rowset changes, matched rowset changes, and reusable stable
  cursor cases.

Application smoke:
- `php lanes/libsqlite/examples/application-cast-rtrim-glob-range-current-source-next127.php --self-test`

Focused verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastRtrimGlobRangeCurrentSourceNext127Test.php`

Non-overlap:
- Avoids accepted UTF-16 RTRIM/GLOB option-name scans, Unicode GLOB range
  handling, CAST residual LIKE/GLOB matching without range candidates,
  expression ORDER BY, JSON table source/cursor/constraint clusters,
  WAL/VFS/B-tree apply clusters, and batch125 UTF-16 RTRIM/GLOB behavior.
  This slice is specifically the CAST-expression RTRIM prefix range plus
  binary GLOB residual current-source invalidation behavior.

Dependency closure:
- No new support component is needed. The slice reuses native PHP
  `SQLiteSelectSql` CAST evaluation, `SQLiteDatabase::globPrefixRangeBounds()`,
  `SQLiteDatabase::globMatches()`, and existing `SQLiteBlobValue` handling.
