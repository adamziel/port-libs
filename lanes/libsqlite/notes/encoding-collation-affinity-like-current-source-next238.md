# Encoding Collation Affinity LIKE Current Source Next238

## Scope

Adds a focused current-source behavior slice for `CAST(option_value AS TEXT)
COLLATE BINARY LIKE ?` over Application-style `wp_options` rows where REAL values
must be textified before LIKE residual matching.

The slice covers SQLite REAL text-affinity details that matter for copied
Application settings: integer-valued REAL values keep a decimal marker
(`100.0`), exponent values keep an exponent marker (`1.0e-05`), INTEGER values
do not gain a decimal marker, and NULL/BLOB operands stay unknown rather than
entering the LIKE rowset. Current/next source invalidation records rowset,
truth, storage, and REAL text-affinity changes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext238Test.php`
  - `1 test files, 82 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next238.php`
  - emits `encoding-collation-affinity-like-current-source-next238` with row `2`
    entering after INTEGER-to-REAL promotion and row `3` exiting after losing
    the REAL decimal marker

## Non-Overlap

Avoids accepted next235 malformed-byte `NOT LIKE` complement behavior, next232
positive malformed-byte LIKE behavior, Unicode GLOB ranges, UTF-16 NOCASE/RTRIM
cursor fences, and unrelated VFS/WAL/B-tree/JSON/SQL executor clusters.

## Dependency Closure

No new support component is needed. The slice reuses native scalar storage
classification, SQLite REAL text-affinity formatting, LIKE prefix/range
planning, residual matching, and current-source cursor invalidation diagnostics.
