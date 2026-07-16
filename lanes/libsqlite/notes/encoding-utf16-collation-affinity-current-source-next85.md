# encoding-utf16-collation-affinity-current-source-next85

- Behavior: adds `SQLiteUtf16CollationAffinityCursor` for current/next cursor boundaries where UTF-16LE/UTF-16BE record text is decoded before SQLite affinity coercion and BINARY/NOCASE/RTRIM collation comparison. It covers numeric coercion of UTF-16 option values, mixed UTF-8/UTF-16 text sources, BLOB/NULL ordering, malformed UTF-16 rejection, and Application copied `wp_options.option_value` seeks.
- Application smoke: `lanes/libsqlite/examples/application-utf16-collation-affinity-current-source-next85.php` reports numeric and RTRIM text seeks over copied option values without requiring `ext/sqlite`.
- Non-overlap: avoids accepted UTF-16 LIKE/GLOB source cursors, accepted Unicode GLOB ranges, malformed UTF-16 record serialization guards, malformed UTF-8 cursor/range work, expression-index collation cursors, SELECT ORDER/GROUP/subquery clusters, JSON table clusters, and VFS/WAL/B-tree storage clusters. The new surface is UTF-16 decoded text feeding affinity comparison at a current-source cursor boundary.
- Dependency closure: no new support component is needed; this reuses existing native `SQLiteAffinityComparison` behavior and adds bounded UTF-16 decode/encode plumbing local to libsqlite.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUtf16CollationAffinityCursor.php
php -l lanes/libsqlite/tests/SQLiteUtf16CollationAffinityCurrentSourceNext85Test.php
php -l lanes/libsqlite/examples/application-utf16-collation-affinity-current-source-next85.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityCurrentSourceNext85Test.php
php lanes/libsqlite/examples/application-utf16-collation-affinity-current-source-next85.php
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: focused PHP PASS-line growth from the new test file. Mapped upstream coverage is unchanged because this is a focused behavior slice over already mapped encoding/collation/affinity inventory.
