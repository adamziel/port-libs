# Encoding UTF-16 affinity LIKE/GLOB current-source next105

This slice extends `SQLiteEncodingAffinityLikeCurrentSourceNextPlan` so the
dynamic-pattern current/next affinity path can evaluate SQLite `GLOB` as well
as `LIKE`. Values and row-supplied patterns are coerced through SQLite text
affinity before matching, BLOB/SQL NULL operands stay non-matching, UTF-16LE
and UTF-16BE encoded bytes are reported for retained current/next rows, and
source/cookie/encoding/storage/text/pattern/rowset invalidation reasons stay
visible for prepared cursor reuse decisions.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingUtf16AffinityLikeGlobCurrentSourceNext105Test.php`
- Result: `1 test files, 65 assertions, 0 failures` with 65 PASS lines.
- `php lanes/libsqlite/examples/application-utf16-affinity-glob-current-source-next105.php --self-test`
- Result: `application-utf16-affinity-glob-current-source-next105 self-test passed`.

Non-overlap: avoids accepted Unicode GLOB range handling, malformed UTF-16
record guards, UTF-16 source-switch cursors, dynamic LIKE affinity, collation
index LIKE/GLOB planning, SELECT SQL text/group/order/subquery clusters, JSON
table source/cursor/constraint clusters, VFS writer/sync/lock/rollback
application, WAL savepoint/checkpoint clusters, and B-tree page-move/overflow
freelist clusters. The new behavior is row-supplied `GLOB` patterns under
SQLite text affinity with UTF-16 current/next invalidation evidence.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP text-affinity, GLOB matcher, and UTF-16 encoding helpers.
