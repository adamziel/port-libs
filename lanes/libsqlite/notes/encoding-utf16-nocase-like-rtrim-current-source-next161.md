# encoding-utf16-nocase-like-rtrim-current-source-next161

Status: focused PHP behavior growth for UTF-16 NOCASE LIKE over RTRIM expression scans when the current source changes collation or LIKE implementation generation.

WordPress path: `wordpress-utf16-nocase-like-rtrim-current-source-next161.php` models copied `wp_options.option_name` rows where a prepared `rtrim(option_name) COLLATE NOCASE LIKE 'plugin!_cache%' ESCAPE '!'` scan keeps the same row family but must reprepare after source/schema, collation generation, LIKE generation, retained RTRIM key, retained NOCASE key, encoding, or byte changes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext161Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next161.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext161Test.php`
  - `1 test files, 75 assertions, 0 failures`
  - `68` PASS lines
- `php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next161.php --self-test`
  - `wordpress-utf16-nocase-like-rtrim-current-source-next161 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +68`, from `72234` to `72302`. Mapped upstream coverage remains unchanged at `609 / 1589`; this is current-source PHP behavior over already mapped encoding/collation/LIKE inventory.

Non-overlap: avoids accepted UTF-16 NOCASE/LIKE/RTRIM row-text next156/next158, UTF-16 pattern/ESCAPE next159, malformed-row isolation next141, UTF-16 LIKE ESCAPE next143, Unicode GLOB ranges, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite evidence work. The new surface is statement invalidation when the source keeps or reuses row candidates but the collation or LIKE implementation generation changes.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE prefix planning, and current-source invalidation metadata.

Next task: continue encoding only on a non-overlapping malformed comparison, collation, affinity, or LIKE/GLOB edge with focused tests.
