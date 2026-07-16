# libsqlite UTF-16 NOCASE LIKE current-source next126

- Scope: adds a focused UTF-16LE/UTF-16BE `LIKE 'plugin%'` NOCASE current-source/next-source planner for copied Application `wp_options.option_name` rows.
- Behavior: records decoded row order through the existing native encoding source cursor, NOCASE LIKE prefix range, UTF-16 range-byte encodings, retained/entered/exited rowids, changed text encodings/key bytes, and reprepare reasons for source, schema, collation version, range bytes, text encoding, key bytes, and rowset changes.
- Non-overlap: avoids accepted Unicode GLOB ranges, malformed UTF-16 insert guards, RTRIM LIKE, CAST/collation LIKE, JSON/B-tree/WAL/VFS slices, and status-only movement.
- Dependency closure: no new support component is needed; this reuses existing native PHP UTF-16 encode/decode and LIKE collation prefix-range helpers.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeCurrentSourceNext126Test.php
php lanes/libsqlite/examples/application-utf16-nocase-like-current-source-next126.php
php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeCurrentSourceNext126Plan.php
php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeCurrentSourceNext126Test.php
php -l lanes/libsqlite/examples/application-utf16-nocase-like-current-source-next126.php
git diff --check -- lanes/libsqlite
```
