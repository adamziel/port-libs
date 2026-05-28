# Encoding Collation Affinity LIKE Current Source Next258

## Scope

Adds a focused WordPress `wp_options.option_name LIKE ? ESCAPE ?` current-source
slice for `PRAGMA case_sensitive_like` transitions. The same escaped pattern is
evaluated under current NOCASE LIKE semantics and next BINARY LIKE semantics,
recording rowset, predicate-truth, and cursor invalidation differences.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Test.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-case-sensitive-like-current-source-next258.php`
- Lint and diff-check required before handoff.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization,
text-affinity coercion, ASCII NOCASE matching, BINARY matching, and
current-source invalidation diagnostics.

## Non-Overlap

This avoids accepted Unicode GLOB ranges, explicit SQL NULL ESCAPE next254,
prepared pattern storage next251, non-ASCII NOCASE prefix next247, UTF-16
malformed guards, and SQL/JSON/WAL/VFS/B-tree clusters.
