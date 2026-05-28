# Encoding Collation Affinity LIKE/GLOB Current Source Next255

- Slice: `encoding-collation-affinity-like-current-source-next255`
- Behavior: adds a current-source diagnostic for `GLOB` bracket-character-class predicates such as `[^A-Za-z]plugin*` where no fixed prefix range can be used and the scan must fall back to residual matching over decoded UTF-8/UTF-16 option names.
- WordPress path: copied `wp_options` option-name scans for transient/plugin diagnostics can preserve UTF-16LE/UTF-16BE decoded bytes and scalar text-affinity coercion while invalidating stale current-source cursors when rowsets or decoded text change.
- Non-overlap: avoids accepted next250 RTRIM `LIKE` residual peers, next251 prepared `LIKE` pattern/ESCAPE affinity, next252 numeric UTF-16 prefix cursor behavior, Unicode GLOB prefix ranges, malformed UTF guards, and JSON/WAL/VFS/B-tree/SQL planner clusters.
- Dependency closure: no new support component needed; reuses lane-local UTF decoder, scalar text-affinity classification, and native GLOB bracket-class residual matching.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Plan.php
php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Test.php
php -l lanes/libsqlite/examples/wordpress-encoding-glob-class-current-source-next255.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 71 assertions, 0 failures
php lanes/libsqlite/examples/wordpress-encoding-glob-class-current-source-next255.php
wordpress-encoding-glob-class-current-source-next255 self-test passed
git diff --check -- lanes/libsqlite
```
