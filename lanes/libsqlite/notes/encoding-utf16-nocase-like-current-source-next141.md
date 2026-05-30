# encoding-utf16-nocase-like-current-source-next141

Status: focused PHP behavior growth for UTF-16 NOCASE LIKE current-source scans with malformed-row isolation.

Application path: `application-utf16-nocase-like-current-source-next141.php` models a copied `wp_options.option_name` prefix scan over mixed UTF-16LE/UTF-16BE rows. Valid plugin cache keys continue to be matched under NOCASE LIKE while malformed current/next text payloads are reported as cursor invalidation diagnostics instead of aborting the whole scan.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeCurrentSourceNext141Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeCurrentSourceNext141Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-nocase-like-current-source-next141.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeCurrentSourceNext141Test.php`
  - `1 test files, 91 assertions, 0 failures`
  - `75` focused PASS lines
- `php lanes/libsqlite/examples/application-utf16-nocase-like-current-source-next141.php --self-test`
  - `application-utf16-nocase-like-current-source-next141 self-test passed`

Expected dashboard movement: `phpPass +75`, from `60841` to `60916`. Mapped upstream coverage remains `606 / 1589`; this is current-source PHP behavior over already mapped encoding, collation, and LIKE inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted UTF-16 NOCASE LIKE next126, UTF-16 RTRIM/NOCASE next103/132, UTF-16 RTRIM LIKE next137, UTF-16 malformed insert/range guards, Unicode GLOB ranges, NOCASE/RTRIM LIKE next134, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite-runner evidence work. The new surface is per-row malformed UTF-16 isolation during a NOCASE LIKE current/next source transition, while valid range candidates remain countable and byte/encoding changes invalidate only retained range rows.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode/encode helpers, LIKE prefix range planning, residual LIKE matching, and current-source cursor invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping malformed-text, collation, affinity, or LIKE/GLOB edge with focused tests.
