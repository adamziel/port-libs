# encoding-utf16-glob-range-current-source-next102

Status: focused PHP behavior growth for UTF-16 GLOB range current-source handoff.

This slice adds `SQLiteUtf16GlobRangeCurrentSourceNextPlan::optionRowNameGlobRange()`. It composes the existing UTF-16 LIKE/GLOB cursor with current/next source metadata so a prepared GLOB range scan over copied `wp_options.option_name` rows can report decoded range bounds, UTF-16LE/UTF-16BE range-bound bytes, retained/exited/entered rowids, renamed key bytes, schema-cookie changes, and whether the cursor can be reused.

Application path: `application-utf16-glob-range-current-source-next102.php` models a plugin option-name GLOB scan across a rebuilt `wp_options` source where one plugin row is renamed, one row enters the `plugin_*` range, and the schema cookie changes.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16GlobRangeCurrentSourceNext102Test.php`
- Result: `1 test files, 62 assertions, 0 failures` with 62 PASS lines.
- `php lanes/libsqlite/examples/application-utf16-glob-range-current-source-next102.php --self-test`
- Result: `application-utf16-glob-range-current-source-next102 self-test passed`

PASS delta: `+62` focused PASS lines. `lane-status.json` `phpPass` moves from `39474` to `39536`. Mapped upstream coverage is unchanged because this reuses already mapped UTF-16 cursor, GLOB prefix range, and current-source invalidation behavior rather than adding a fresh upstream denominator unit.

Non-overlap: this avoids accepted Unicode GLOB matcher/range behavior, malformed UTF-16 insert guards, malformed UTF-16 LIKE/GLOB range admission, option-value affinity LIKE/GLOB scans, source-switch rowset comparison, LIKE current/next cursor ranges, encoding collation index LIKE/GLOB planning, JSON/VFS/WAL/B-tree current-source clusters, and SELECT SQL executor clusters. The new surface is encoded UTF-16 GLOB range-bound provenance and cursor reuse/reprepare reasons at the current-source to next-source boundary.

Dependency closure: no new support component is needed. The slice reuses lane-local UTF-16 encoding/decoding, GLOB prefix range bounds, residual GLOB matching, and current/next cursor diagnostics.

Next task: continue encoding work only on a non-overlapping malformed-text or collation/affinity predicate edge that adds focused tests; otherwise pivot to another current-source closure bucket.
