## encoding-collation-affinity-glob-current-source-next239

Slice: malformed UTF-8 byte `GLOB` bracket/range comparison after text affinity for copied Application `wp_options.option_value` rows.

Behavior:

- Adds `SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan`.
- Preserves SQLite-style GLOB tokenization where well-formed UTF-8 codepoints stay intact and malformed bytes are consumed one byte at a time.
- Records current/next cursor invalidation for source-name, schema-cookie, matched-rowset, byte, storage-class, and token-count changes.
- Covers scalar text affinity for integer, float, and boolean values while leaving BLOB and SQL NULL values outside implicit GLOB matching.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityGlobCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityGlobCurrentSourceNext239Test.php`
- `php -l lanes/libsqlite/examples/application-encoding-collation-affinity-glob-current-source-next239.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityGlobCurrentSourceNext239Test.php`
  - `1 test files, 70 assertions, 0 failures`
  - 61 focused `PASS` lines
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-glob-current-source-next239.php --self-test`
  - `application-encoding-collation-affinity-glob-current-source-next239 self-test passed`

Non-overlap:

- Avoids accepted Unicode GLOB ranges, malformed-byte LIKE next232, BLOB LIKE/GLOB next234, UTF-16 NOCASE/RTRIM LIKE cursor fences, VFS/WAL/B-tree/JSON/SQL executor clusters, and status-only suite evidence.

Dependency closure:

- No new support component needed. The slice reuses native GLOB tokenization, text affinity, binary bracket ranges, scalar storage classification, and current-source cursor diagnostics.

Next task:

- Continue encoding work on a distinct collation/affinity comparison edge, preferably one not already covered by Unicode GLOB, malformed-byte LIKE, BLOB admission, or UTF-16 RTRIM/NOCASE current-source slices.
