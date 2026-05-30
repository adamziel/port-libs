# Encoding affinity LIKE current-source next94

Status: focused PHP behavior growth for current/next Application option-value LIKE/GLOB scans after SQLite text-affinity coercion.

Behavior:
- Added `SQLiteEncodingAffinityLikeCurrentSourceNextPlan::optionRowValuePlan()` to compare current and next `wp_options` value scans using the existing UTF-16 LIKE/GLOB affinity cursor.
- The plan reports matched rowset deltas plus retained-row changes in text-affinity output, original storage class, scan encoding, encoded bytes, schema/source identity, and invalidation reasons.
- BLOB and SQL NULL values remain non-text operands for LIKE/GLOB scans, while integer, real, boolean, and text option values are coerced through SQLite-style text affinity before matching and UTF-16 byte encoding.
- The focused test covers escaped LIKE literals, GLOB prefix ranges, Unicode character classes, emoji suffix changes, numeric/boolean text-affinity matches, source switches, malformed text guards, unsupported operator/encoding guards, and stable reusable cursors.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`
- `php -l lanes/libsqlite/src/SQLiteEncodingAffinityLikeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`
- `php -l lanes/libsqlite/examples/application-option-value-affinity-like-current-next94.php`
- `php lanes/libsqlite/examples/application-option-value-affinity-like-current-next94.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement:
- `phpPass`: `36393 -> 36462` from 69 newly passing focused PASS lines in `SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`.
- `benchmarkDenominator.mapped`: unchanged at `534 / 1589`; this is current-source PHP coverage over the existing encoding/collation/affinity LIKE family, not a fresh upstream denominator unit.

Dependency closure:
- No new support component is needed. This reuses existing native text-affinity coercion, UTF-16 encoding, LIKE/GLOB matching, BLOB value representation, and current-source cursor helpers.

Non-overlap:
- Avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards, UTF-16 RTRIM option-name cursor behavior, encoding index LIKE/GLOB invalidation, JSON/VFS/WAL/B-tree batch90 surfaces, and queued LIKE current/next cursor ranges. The new surface is option-value current/next invalidation after text-affinity coercion and scan-encoding changes.
