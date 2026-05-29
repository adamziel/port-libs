# UTF-16 NOCASE LIKE RTRIM current-source next227

Status: focused PHP behavior growth for `encoding-utf16-nocase-like-rtrim-current-source-next227`.

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for the current-source boundary where `rtrim(option_name) COLLATE NOCASE LIKE ?` is planned over UTF-16 `wp_options` option-name bytes. It specifically proves that RTRIM trims trailing ASCII space only: UTF-16 NBSP and tab suffixes stay in the residual LIKE text and do not match an equality-style pattern after trimming.

WordPress smoke: `wordpress-utf16-nocase-like-rtrim-current-source-next227.php` models a copied plugin cache option scan where a row moves from NBSP-suffixed to ASCII-space-suffixed text across the current/next source boundary, invalidating the old cursor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext227Test.php`
  - `1 test files, 66 assertions, 0 failures`
  - `62` focused PASS lines
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext227Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next227.php`
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next227.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next227 self-test passed`

Expected dashboard movement: `phpPass +62` from the new focused PASS lines. `benchmarkDenominator.mapped` remains unchanged; this reuses already mapped UTF-16 decode, LIKE/NOCASE prefix, RTRIM expression-key, and current-source invalidation inventory.

Non-overlap: avoids accepted next194 escaped wildcard literal prefix behavior, next219 supplementary-plane underscore wildcard behavior, next200/211/212/213 prepared escape rebinding, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner/status-only clusters. The new surface is ASCII-space-only RTRIM residual equality under UTF-16 NOCASE LIKE across current/next source transitions.

Dependency closure: no new support component is needed. The slice reuses lane-local native UTF-16 decoding, ASCII NOCASE LIKE prefix planning, and RTRIM expression-key behavior.
