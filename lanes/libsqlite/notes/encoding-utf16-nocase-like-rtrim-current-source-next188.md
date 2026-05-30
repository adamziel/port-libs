# Encoding UTF-16 NOCASE LIKE RTRIM current-source next188

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ?` scans when a deleted last-yielded rowid is reused by a non-matching next-source row.

Application path: `application-utf16-nocase-like-rtrim-current-source-next188.php` models a copied `wp_options.option_name` prefix scan where rowid `2` was last yielded as `plugin_cache`, then the next source reuses rowid `2` for `theme_cache_reused_rowid`. The plan now fences deleted-token resume on raw next-source rowid reuse before continuing from the old `(RTRIM/NOCASE key, rowid)` boundary.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next188.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Test.php`
  - `1 test files, 68 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next188.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next188 self-test passed`

Expected dashboard movement: `phpPass +68`, from `89524` to `89592`. Mapped upstream coverage remains `616 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE, and cursor-resume inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next185 deleted-token replay, next184 escaped token residual validation, next181 peer replay, next168 case-sensitive LIKE toggling, UTF-16 malformed insert guards, Unicode GLOB ranges, and JSON/VFS/WAL/B-tree/SQL executor clusters. The new surface is specifically next-source rowid reuse fencing before deleted-token resume.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, deleted-token replay diagnostics, and current-source rowid fence checks.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
