# encoding-utf16-nocase-like-rtrim-current-source-next190

Status: focused PHP behavior growth for UTF-16 `rtrim(option_name) COLLATE NOCASE LIKE ?` prefix cursors when a current-source refresh changes only trailing whitespace classes.

Behavior:

- Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`.
- Covers SQLite's ASCII-space-only `RTRIM` boundary: trailing ASCII spaces are stripped, but tab, newline, and non-breaking space remain part of the expression key.
- Reports retained prefix-cursor rows whose `RTRIM` / ASCII `NOCASE` keys change across current/next sources even while the `LIKE 'plugin%'` row remains a candidate and residual match.
- Keeps malformed UTF-16 rows isolated from the cursor, preserving existing decode diagnostics.

WordPress path:

- `wordpress-utf16-nocase-like-rtrim-current-source-next190.php` models copied `wp_options.option_name` rows where plugin option names move between trailing ASCII spaces, tabs, and non-breaking spaces during a source handoff. The plan invalidates a retained prefix cursor because the expression key changed, rather than reusing stale `RTRIM` ordering.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext190Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next190.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext190Test.php`
  - `1 test files, 73 assertions, 0 failures`
  - `65` PASS lines
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next190.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next190 self-test passed`

Expected dashboard movement: `phpPass +65`, from `90822` to `90887`. Mapped upstream coverage remains `617 / 1589`; this is focused PHP behavior over already mapped UTF-16, NOCASE/RTRIM, LIKE, and current-source inventory.

Non-overlap:

- Avoids accepted next187 dangling `ESCAPE` residual checks, next183 ASCII prefix range reuse, next184 escaped peer replay, prepared-pattern byte normalization, Unicode GLOB ranges, malformed UTF-16 insert guards, JSON/VFS/WAL/B-tree/SQL executor clusters, and suite-runner evidence work.
- The new surface is specifically ASCII-space-only `RTRIM` boundary invalidation for retained UTF-16 NOCASE/RTRIM LIKE current-source prefix cursor rows.

Dependency closure:

- No new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
