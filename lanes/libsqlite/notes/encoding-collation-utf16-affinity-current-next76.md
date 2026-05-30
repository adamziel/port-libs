# encoding-collation-utf16-affinity-current-next76

Status: focused PHP behavior growth for SQLite `RTRIM` collation parity across
affinity comparison, SELECT CASE collation dispatch, and GLOB current/next
cursor diagnostics.

Behavior:
- SQLite's built-in `RTRIM` collation ignores only trailing ASCII space
  (`0x20`). It does not trim tabs, newlines, carriage returns, NUL bytes, or
  vertical tabs.
- `SQLiteAffinityComparison`, `SQLiteSelectExpression`, and
  `SQLiteLikeCurrentNextCursor` now use space-only trimming for `RTRIM`; the
  new current/next cursor assertions exercise the already space-only GLOB path
  at exact-prefix boundaries.
- The copied `wp_options` smoke proves `siteurl ` compares with `siteurl`, while
  `siteurl\t` and `siteurl\n` remain distinct option names.

Verification:
- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php`
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/src/SQLiteLikeCurrentNextCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteRtrimCollationGlobCursorTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rtrim-collation-glob-cursor.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRtrimCollationGlobCursorTest.php`
- `php lanes/libsqlite/examples/wordpress-rtrim-collation-glob-cursor.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap:
This avoids accepted Unicode GLOB range behavior, malformed UTF-8 before UTF-16
record serialization guards, LIKE current/next cursor ranges, generic affinity
storage-class comparisons, VFS/WAL/B-tree/JSON/SELECT GROUP BY and subquery
clusters, and batch70/71 malformed-text collation/range cursor work. The new
surface is the narrower upstream `RTRIM` rule that only ASCII space is ignored
at the current/next comparison boundary.

Dependency closure:
No new support component is needed; this reuses existing native PHP comparison,
SELECT SQL, and current/next cursor infrastructure.
