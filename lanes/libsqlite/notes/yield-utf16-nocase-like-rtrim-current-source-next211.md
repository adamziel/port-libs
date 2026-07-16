# UTF-16 NOCASE LIKE RTRIM current-source next211

This slice adds focused coverage for refreshing a Application `wp_options`
`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` cursor when the current
source changes under mixed UTF-8, UTF-16LE, and UTF-16BE row storage. The plan
decodes option-name bytes, applies ASCII-space `RTRIM` before ASCII-only
`NOCASE` range keys, runs the `LIKE` residual, and distinguishes byte-order-only
refreshes that can reuse a cursor from rowset/source/schema changes that must
invalidate it.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext211Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next211.php`
- PHP lint on the changed PHP files
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decode, `NOCASE` LIKE prefix ranges, RTRIM expression keys, and
current-source cursor diagnostics.

Non-overlap: this avoids accepted BOM normalization next206, escape rebind
next200, no-prefix next203, escaped literal next194/195, dangling ESCAPE
next187, Unicode GLOB ranges, malformed UTF-16 insert guards, and accepted
next209 UTF-16 NOCASE/LIKE/RTRIM coverage by focusing on source-refresh rowset
invalidation versus byte-order-only reuse.
