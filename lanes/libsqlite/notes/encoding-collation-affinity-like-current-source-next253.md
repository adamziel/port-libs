# encoding-collation-affinity-like-current-source-next253

Status: focused PHP behavior growth for UTF-16 decoded `option_value` values feeding a TEXT-affinity cursor for a `LIKE` predicate under ASCII `NOCASE`.

Behavior:
- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Plan`.
- Models WordPress copied `wp_options` rows where `option_value COLLATE NOCASE LIKE 'yes%'` must decode UTF-8/UTF-16 text, apply TEXT affinity to integer/real values before LIKE cursor admission, keep BLOB values out of this text-affinity cursor, and invalidate current-source cursor reuse when source/schema, decoded text, storage class, encoding bytes, or residual LIKE rowsets change.
- The WordPress smoke `wordpress-encoding-affinity-like-current-source-next253.php` exercises autoload/import option values moving from `YES-cache` to `no-cache` and from `no` to `YES-new` without requiring `ext-sqlite3`.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Test.php`
  - `1 test files, 76 assertions, 0 failures`

Expected dashboard movement: `phpPass +76`, from `131296` to `131372`. Mapped upstream coverage remains `663 / 1589`; this is focused PHP behavior over already mapped encoding/collation/affinity/LIKE inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted option-name UTF-16 RTRIM/NOCASE LIKE current-source slices through next233, accepted next249/next250 encoding LIKE behavior, Unicode GLOB ranges, malformed UTF-16 insert/range guards, SELECT predicate affinity-only next109/next120, VFS/WAL/B-tree/JSON/planner clusters, and suite next253 veryquick evidence. The new surface is option-value TEXT affinity after UTF-16 decode before `LIKE`, with current/next invalidation over storage-class and encoding changes.

Dependency closure: no new support component is needed. The slice reuses native UTF-16 decode/encode helpers, TEXT affinity coercion, ASCII NOCASE LIKE prefix planning, and current-source diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused test growth.
