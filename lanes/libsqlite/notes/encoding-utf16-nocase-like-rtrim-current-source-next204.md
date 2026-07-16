# encoding-utf16-nocase-like-rtrim-current-source-next204

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source scans whose fixed prefix contains non-ASCII bytes.

Application path: `application-utf16-nocase-like-rtrim-current-source-next204.php` models copied `wp_options.option_name` prefix scans for localized plugin keys such as `plugin_éclair`. SQLite's default NOCASE LIKE folds ASCII only, so a non-ASCII fixed prefix cannot be admitted to the NOCASE prefix-range cursor. The planner falls back to a decoded full scan, applies the LIKE residual after RTRIM, keeps `é` and `É` distinct, and isolates malformed UTF-16 rows without aborting the valid rowset.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext204Test.php`
  - `1 test files, 72 assertions, 0 failures`
  - `68` PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next204.php --self-test`
  - `application-utf16-nocase-like-rtrim-current-source-next204 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext204Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next204.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68`, from `99392` to `99460`. Mapped upstream coverage remains `621 / 1589`; this is focused PHP behavior over already mapped UTF-16, NOCASE, RTRIM, and LIKE current-source inventory rather than a new manifest row.

Non-overlap: avoids accepted next203 no-fixed-prefix full-scan fallback, next202 source-row LIKE patterns, next200 ESCAPE rebinding, next194 escaped wildcard literal-prefix diagnostics, Unicode GLOB ranges, malformed UTF-16 insert guards, and VFS/WAL/B-tree/JSON/SQL planner clusters. The new surface is specifically non-ASCII fixed-prefix NOCASE LIKE fallback over decoded UTF-16 RTRIM rows.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode, ASCII-only NOCASE LIKE residual matching, RTRIM expression keys, and current-source diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
