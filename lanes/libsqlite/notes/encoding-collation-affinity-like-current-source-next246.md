# encoding-collation-affinity-like-current-source-next246

Status: focused PHP behavior growth for dynamic `LIKE ... ESCAPE` operand
affinity and current-source cursor reuse.

Behavior:
- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan` for copied
  Application `wp_options.option_value` scans where the `ESCAPE` operand is a
  bound value rather than a fixed SQL literal.
- Applies SQLite-style text affinity to integer, boolean, float, and text
  escape operands before validating that the escape is exactly one SQLite
  pattern character.
- Reports NULL/BLOB/multi-character escape operands as unknown or malformed
  cursor fences, and invalidates current-source reuse when the escape operand
  storage/text changes between current and next sources.
- Keeps LIKE residual behavior separate from collation keys: ASCII NOCASE is
  recorded for ordering/invalidation, while the dynamic ESCAPE operand controls
  literal `_` / `%` interpretation.

Application path:
- `application-dynamic-escape-like-current-source-next246.php` models plugin
  option values such as `plugin_%enabled` where a migration/import preview uses
  a user-provided escape character. Rebinding the escape changes the visible
  rowset and must force a cursor recheck before publishing the next source.

Verification:
- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Test.php`
- `php -l lanes/libsqlite/examples/application-dynamic-escape-like-current-source-next246.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-dynamic-escape-like-current-source-next246.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Test.php`
  - `1 test files, 88 assertions, 0 failures`
  - `71` focused PASS lines
- `php lanes/libsqlite/examples/application-dynamic-escape-like-current-source-next246.php --self-test`
  - `application-dynamic-escape-like-current-source-next246 self-test passed`

Expected dashboard movement: `phpPass +71`, from `125265` to `125336`.
Mapped upstream coverage remains `650 / 1589`; this is focused PHP behavior
over already mapped LIKE/ESCAPE/collation/affinity inventory rather than a
fresh manifest-backed upstream row.

Non-overlap: avoids accepted fixed escaped wildcard next236/next237,
malformed-byte LIKE/NOT LIKE next232/next235, BLOB LIKE/GLOB admission
next234, REAL/numeric LIKE next238/next240, byte-aware/embedded-NUL/RTRIM
LIKE next241-next243, UTF-16 NOCASE/RTRIM cursor fences, Unicode GLOB ranges,
and VFS/WAL/B-tree/JSON/SQL executor clusters. The new surface is dynamic
ESCAPE operand affinity and rebind fencing.

Dependency closure: no new support component is needed. The slice reuses native
LIKE residual matching, scalar text-affinity conversion, ASCII NOCASE collation
keys, `SQLiteBlobValue`, and current-source invalidation diagnostics.
