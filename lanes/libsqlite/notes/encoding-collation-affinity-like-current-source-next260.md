# encoding-collation-affinity-like-current-source-next260

This slice adds a focused Application `wp_options.option_name` RTRIM-collated
`LIKE` residual plan. The behavior covered is the SQLite edge where a cursor
may admit rows by an RTRIM collation key, but the final `LIKE` residual still
uses raw text bytes and does not trim trailing spaces.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext260Test.php`:
  `1 test files, 78 assertions, 0 failures`.
- Local Application smoke: `php lanes/libsqlite/examples/application-rtrim-like-residual-next260.php`.
- PHP lint: `php -l` for the changed source, test, and example files.
- Diff hygiene: `git diff --check -- lanes/libsqlite`.

Dependency closure: no new support component is needed; this reuses native
UTF-8/UTF-16 decode, scalar text-affinity coercion, LIKE residual matching, and
RTRIM collation-key diagnostics.

Non-overlap: this avoids accepted next255 GLOB bracket fallback, next256 dynamic
pattern affinity, Unicode GLOB ranges, UTF-16 malformed guards, JSON, WAL, VFS,
B-tree, and SQL planner clusters.
