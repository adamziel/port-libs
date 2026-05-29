# encoding-utf16-nocase-like-rtrim-current-source-next229

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` scans where rows end in non-ASCII Unicode whitespace.

Behavior: `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` reuses the accepted UTF-16 NOCASE/RTRIM LIKE keyset path and adds diagnostics proving SQLite `RTRIM` trims only ASCII space. UTF-16 NBSP, narrow NBSP, thin space, and ideographic space remain part of the comparison key and residual LIKE text, while visually similar current/next rowsets invalidate a resumed WordPress option-name cursor.

WordPress smoke: `examples/wordpress-utf16-nocase-like-rtrim-current-source-next229.php`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext229Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next229.php --self-test`
- PHP lint on changed PHP files
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses native UTF-16 decode, LIKE ESCAPE prefix planning, ASCII-only RTRIM keys, NOCASE keyset resume, and current-source invalidation diagnostics.

Non-overlap: avoids accepted next224 keyset rowsets, next212/213 Unicode ESCAPE handling, next190 ASCII-space trim boundary behavior, next211 source-refresh coverage, Unicode GLOB ranges, malformed UTF-16 insert guards, and VFS/WAL/B-tree/JSON/SQL clusters. The new surface is specifically non-ASCII Unicode whitespace retention under UTF-16 RTRIM/NOCASE LIKE current-source comparisons.
