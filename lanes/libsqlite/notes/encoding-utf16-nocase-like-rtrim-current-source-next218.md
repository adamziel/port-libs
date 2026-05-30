# encoding-utf16-nocase-like-rtrim-current-source-next218

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source yield windows.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`. It composes the accepted UTF-16 prepared `ESCAPE` decode and RTRIM/NOCASE LIKE residual scan with `ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT/OFFSET` page-window fencing. A copied `wp_options` import can only reuse the current-source page token when source, schema cookie, pattern, escape, offset/limit, page rowids, and tail key still match.

Application smoke: `application-utf16-nocase-like-rtrim-current-source-next218.php` covers a copied `wp_options` UTF-16 option-name scan where a new `PLUGIN_CACHE_AARDVARK` row changes the matched rowset while the current page remains stable.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext218Test.php`
  - `1 test files, 83 assertions, 0 failures`
  - `75` PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next218.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next218 self-test passed`

Expected dashboard movement: `phpPass +75`, from `104546` to `104621`. Mapped upstream coverage remains `623 / 1589`; this is focused current-source PHP behavior over already mapped UTF-16, NOCASE, RTRIM, LIKE, and yield-cursor inventory.

Non-overlap: avoids accepted next208 prepared ESCAPE decode, next203 no-prefix full scans, next200 escape rebinding, next185/next170 resume-token replay, Unicode GLOB ranges, UTF-16 malformed insert guards, and VFS/WAL/B-tree/JSON/SQL executor clusters. The new surface is the LIMIT/OFFSET yield-window fence after UTF-16 RTRIM/NOCASE LIKE residual matching.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source yield cursor diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, malformed-text comparison, or LIKE/GLOB edge with focused tests.
