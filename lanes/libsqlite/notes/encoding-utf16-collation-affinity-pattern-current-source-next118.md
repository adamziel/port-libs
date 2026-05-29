# encoding-utf16-collation-affinity-pattern-current-source-next118

Status: focused PHP behavior growth for UTF-16 decoded LIKE/GLOB patterns with collation-aware range provenance.

This slice adds `SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::wordpressOptionValuePlan()`. It decodes UTF-8/UTF-16LE/UTF-16BE pattern and escape bytes, reuses existing option-value affinity matching, and adds BINARY/NOCASE/RTRIM range admission metadata plus current/next UTF-16 range-byte provenance for cursor invalidation.

WordPress path: `wordpress-utf16-collation-affinity-pattern-current-source-next118.php` models copied `wp_options.option_value` scans for autoload prefixes, literal percent escapes, and numeric option values during a current-source to next-source rebuild.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16CollationAffinityPatternCurrentSourceNext118Test.php`
- `php -l lanes/libsqlite/examples/wordpress-utf16-collation-affinity-pattern-current-source-next118.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityPatternCurrentSourceNext118Test.php`
- `php lanes/libsqlite/examples/wordpress-utf16-collation-affinity-pattern-current-source-next118.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: expected `phpPass` +58 from the focused test's PASS lines. Mapped upstream coverage is unchanged because this is a focused PHP behavior slice over already mapped UTF-16, LIKE/GLOB, affinity, and current-source planner inventory.

Non-overlap: avoids accepted UTF-16 malformed insert guards, malformed UTF-16 LIKE/GLOB ranges, Unicode GLOB range matching, static UTF-16 GLOB range current-source plans, next114 decoded UTF-16 pattern matching, numeric-affinity-only behavior, VDBE sorter collation, SQL predicate LIKE/GLOB, JSON/VFS/WAL/B-tree current-source clusters, and status-only evidence. The new surface is collation-aware range admission and encoded range-byte invalidation for decoded UTF-16 patterns.

Dependency closure: no new support component is needed. This reuses native PHP UTF-16 decode/encode, LIKE/GLOB matchers, collation range planning, and existing affinity coercion.
