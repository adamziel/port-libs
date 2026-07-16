# UTF-16 NOCASE LIKE RTRIM current-source next206

This slice adds focused coverage for prepared UTF-16 `LIKE` RHS patterns that
carry a byte-order mark. The new plan strips a leading UTF BOM before deriving
the `NOCASE` prefix range for `rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE
?`, then records how a stale cursor using the raw BOM-prefixed pattern would
miss Application option rows.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext206Test.php`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next206.php`
- PHP lint on the changed PHP files
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decode, prepared LIKE RHS normalization, ASCII `NOCASE` prefix ranges,
RTRIM expression keys, and current-source cursor diagnostics.

Non-overlap: this avoids accepted escape rebind next200, no-prefix next203,
escaped literal next194/195, dangling ESCAPE next187, Unicode GLOB ranges, and
malformed UTF-16 insert guards.
