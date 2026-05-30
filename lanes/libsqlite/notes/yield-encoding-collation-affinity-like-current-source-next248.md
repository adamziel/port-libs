# encoding-collation-affinity-like-current-source-next248

## Behavior

Adds a focused current-source plan for SQLite `LIKE ... ESCAPE` when the
escape expression is a single non-ASCII character. The slice proves that `é`
is one SQLite pattern character, escaped `_` and `%` become literal prefix
characters, UTF-16 option-name bytes are decoded before NOCASE prefix range
comparison, and the residual LIKE check still rejects rows whose decoded text
lost the literal `%` between current and next sources.

This is Application-relevant for copied `wp_options` scans where import filters
bind escaped literal option-name probes from user data and the database may
contain mixed UTF-8, UTF-16LE, and UTF-16BE text records.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext248Test.php`
  - `1 test files, 93 assertions, 0 failures`
  - `81` PASS lines
- `php lanes/libsqlite/examples/application-nonascii-escape-like-current-source-next248.php`
  - emitted `encoding-collation-affinity-like-current-source-next248`
- PHP lint for changed PHP files
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids accepted next245 dangling ASCII ESCAPE residuals, next242
embedded-NUL value LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards,
current JSON/WAL/B-tree/VFS surfaces, and status-only suite evidence.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization,
UTF-16 decode guards, escaped-prefix range planning, ASCII-only NOCASE
comparison, and current-source invalidation diagnostics.
