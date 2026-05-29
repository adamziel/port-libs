# encoding-utf16-nocase-like-rtrim-current-source-next226

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ?` scans across composed and decomposed Unicode text.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next226.php` models copied `wp_options.option_name` rows where plugin option keys can contain UTF-16 accented text. SQLite does not normalize Unicode for `NOCASE LIKE`: composed `é` and decomposed `e` plus combining acute remain distinct byte/code point sequences, `_` consumes one decoded code point, `RTRIM` trims only ASCII space, and `NOCASE` folds ASCII only.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext226Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next226.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext226Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next226.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +81`, from `108262` to `108343` on top of the batch198 lane status in this worktree. Mapped upstream coverage remains `625 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE, LIKE, RTRIM, and current-source inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next219 supplementary-plane wildcard handling, next209 ASCII-space RTRIM diagnostics, prepared ESCAPE/pattern rebind slices, Unicode GLOB ranges, malformed UTF-16 insert guards, storage/VFS/WAL/B-tree/planner clusters, and suite-runner evidence work. The new surface is the Unicode normalization boundary for combining marks in UTF-16 LIKE residual matching.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode helpers, LIKE prefix planning, RTRIM expression keys, ASCII-only NOCASE residual matching, and binary-safe Unicode code point splitting.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
