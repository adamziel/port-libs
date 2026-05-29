# encoding-utf16-nocase-like-rtrim-current-source-next210

Status: focused PHP behavior growth for UTF-16 RTRIM/NOCASE LIKE current-source scans with embedded NUL text and pattern bytes.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next210.php` models copied `wp_options.option_name` rows that contain an embedded NUL from a migration/import artifact. SQLite text comparison does not truncate at NUL, so `rtrim(option_name) COLLATE NOCASE LIKE 'plugin\0cache%'` keeps the NUL inside the prefix range and residual match.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext210Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next210.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext210Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next210.php --self-test`

Expected dashboard movement: `phpPass +79`, from `102317` to `102396`. Mapped upstream coverage remains `622 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE, LIKE, and RTRIM inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted batch190/next208 UTF-16 NOCASE/LIKE/RTRIM behavior, next209 ASCII-space RTRIM diagnostics, BOM normalization, escape rebind, no-fixed-prefix full scans, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters. The new surface is embedded NUL bytes in decoded UTF-16 text and LIKE pattern prefixes.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and binary-safe PHP string residual matching.

Next task: continue encoding only on a distinct collation/affinity or malformed comparison edge, or leave further UTF-16 LIKE/RTRIM variants until an upstream runner blocker identifies a fresh gap.
