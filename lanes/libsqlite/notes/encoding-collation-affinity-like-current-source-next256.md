# Encoding collation affinity LIKE current-source next256

Status: focused PHP behavior growth for LIKE pattern operand TEXT affinity across a current/next source boundary.

Behavior:
- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Plan`.
- Applies SQLite scalar TEXT affinity to the dynamic LIKE pattern operand before prefix-range planning and residual matching.
- Tracks integer, real, boolean, text, NULL, BLOB, malformed text, candidate rowset, matched rowset, collation key, and source/schema-cookie cursor invalidation.
- Keeps BLOB row values out of text LIKE matching and reports them as malformed text inputs instead of silently matching byte payloads.

Application path: `application-encoding-collation-affinity-like-current-source-next256.php` models copied `wp_options` scans where a prepared LIKE pattern changes from a plugin text prefix to a numeric autoload/value prefix before cursor reuse.

Verification:
- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Test.php`
- `php -l lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next256.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Test.php`
  - `1 test files, 82 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next256.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +82`, from `134837` to `134919`. Mapped upstream coverage remains `674 / 1589`; this is focused PHP behavior over already mapped encoding/collation/LIKE inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next253 fixed-pattern value affinity, next246 dynamic ESCAPE affinity, UTF-16 NOCASE/RTRIM cursor work, Unicode GLOB ranges, malformed UTF-16 insert guards, JSON/VFS/WAL/B-tree/planner clusters, and suite countability evidence. The new surface is the LIKE pattern operand affinity fence across current/next source reuse.

Dependency closure: no new support component is needed. The slice reuses native LIKE matching, scalar text-affinity conversion, ASCII NOCASE/RTRIM collation keys, and current-source diagnostics.

Next task: continue encoding work only on a non-overlapping collation, malformed-text comparison, affinity, LIKE, or GLOB edge with focused tests.
