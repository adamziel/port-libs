# encoding-utf16-nocase-like-rtrim-current-source-next168

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ?` scans when a current/next source changes `case_sensitive_like`.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next168.php` models copied `wp_options.option_name` rows where a default ASCII-NOCASE LIKE scan can use a NOCASE/RTRIM prefix range, but the next source toggles case-sensitive LIKE. The plan preserves the old candidate range only as recheck evidence, rejects cursor reuse, reports uppercase false positives, and requires binary LIKE scan/reprepare.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext168Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next168.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext168Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next168.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +72` from the new focused test file (`84` raw assertions). Mapped upstream coverage remains unchanged; this is current-source behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE, and current-source inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next156/158 UTF-16 row-side NOCASE/RTRIM LIKE scans, next159/160/162 prepared pattern byte normalization, next163 RHS `rtrim(pattern)`, next164 yield/resume statement fingerprinting, UTF-16 malformed insert guards, Unicode GLOB ranges, JSON/VFS/WAL/B-tree/SQL executor clusters, and suite-runner evidence work. The new surface is specifically `case_sensitive_like` toggling residual LIKE semantics while an old NOCASE/RTRIM prefix range still contains uppercase false positives.

Dependency closure: no new support component is needed. The slice reuses lane-local UTF-16 decode helpers, RTRIM expression keys, LIKE prefix planning, and ASCII-sensitive LIKE matching.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
