# encoding-utf16-nocase-like-rtrim-current-source-next203

Status: focused PHP behavior growth for UTF-16 NOCASE/RTRIM LIKE scans whose pattern has no fixed prefix.

Application path: `application-utf16-nocase-like-rtrim-current-source-next203.php` models copied `wp_options.option_name` scans such as `rtrim(option_name) COLLATE NOCASE LIKE '%cache'`. Because the LIKE prefix is empty, the source transition cannot reuse a prefix range cursor; it must decode all valid UTF-8/UTF-16 rows, isolate malformed rows, apply RTRIM before the residual LIKE check, and invalidate when the matched full-scan rowset changes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext203Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next203.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext203Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next203.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +73`, from `97068` to `97141`. Mapped upstream coverage is unchanged at `619 / 1589`; this reuses existing encoding/collation/LIKE inventory rather than adding a fresh upstream manifest row.

Non-overlap: avoids accepted UTF-16 NOCASE/RTRIM LIKE escaped-prefix next194, escaped-tail next195, duplicate-peer resume next196, prepared/replay/yield/resume token slices, UTF-16 malformed insert guards, Unicode GLOB ranges, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite evidence work. The new surface is no-fixed-prefix full-scan current-source invalidation for UTF-16 RTRIM/NOCASE LIKE residuals.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode helpers, SQLite LIKE pattern planning, RTRIM expression keys, residual LIKE matching, and lane-local current-source diagnostics.

Next task: continue encoding only on a non-overlapping malformed-text comparison, collation, affinity, or LIKE/GLOB edge; otherwise pivot to another closure bucket.
