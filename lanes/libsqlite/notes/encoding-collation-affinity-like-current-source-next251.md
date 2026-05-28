# encoding-collation-affinity-like-current-source-next251

Status: focused PHP behavior growth for prepared LIKE pattern and ESCAPE
affinity changes across copied WordPress option sources.

This slice adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Plan`.
It models `option_value LIKE ? ESCAPE ?` scans where the prepared pattern or
ESCAPE binding keeps the same text after SQLite text affinity but changes
storage class between current and next sources. The plan reports pattern text,
pattern bytes, storage class, prefix range, matched rowsets, retained value
storage changes, and cursor invalidation reasons.

WordPress path: `wordpress-prepared-pattern-affinity-like-current-source-next251.php`
models copied `wp_options` imports where plugin code rebinds a numeric pattern
as text while option values also change storage class.

Evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Test.php`
  - `1 test files, 72 assertions, 0 failures`
  - 72 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-prepared-pattern-affinity-like-current-source-next251.php --self-test`
  - `wordpress-prepared-pattern-affinity-like-current-source-next251 self-test passed`

Expected dashboard movement: `phpPass +72`, from `129612` to `129684`.
Mapped upstream coverage remains `659 / 1589`; this is focused PHP behavior
over already mapped encoding/collation/affinity LIKE inventory rather than a
fresh manifest-backed upstream row.

Non-overlap: avoids accepted numeric option-value LIKE next240, embedded-NUL
option-name LIKE next241, mixed UTF option-name LIKE next244, escaped
option-name LIKE next236, UTF-16 NOCASE/RTRIM cursor fences, Unicode GLOB
ranges, malformed UTF guards, and storage/planner clusters. The new surface is
prepared pattern and ESCAPE storage-class transition as a current-source fence
even when the decoded LIKE text is unchanged.

Dependency closure: no new support component is needed. The patch reuses
native LIKE tokenization, scalar storage classification, numeric/boolean text
affinity, and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation,
affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
