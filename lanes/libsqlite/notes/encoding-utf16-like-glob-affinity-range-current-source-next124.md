# encoding-utf16-like-glob-affinity-range-current-source-next124

Status: focused PHP behavior growth for UTF-16 LIKE/GLOB pattern bytes feeding affinity range current-source scans.

This slice adds `SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan`. It decodes UTF-16LE/UTF-16BE/UTF-8 pattern and ESCAPE bytes, validates malformed UTF-16 and one-character `LIKE ESCAPE`, delegates matching to the existing affinity range current/next scanner, and records UTF-16LE/UTF-16BE range-bound bytes so a prepared Application `wp_options.option_value` range scan can explain why the next source needs reprepare.

Application path: `application-utf16-like-glob-affinity-range-current-source-next124.php` models a copied `wp_options` import scan using a UTF-16 `LIKE` pattern over option values. It reports decoded pattern provenance, encoded range bounds, retained/entered rows, changed affinity text, and schema-cookie invalidation.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNext124Test.php`
  - `1 test files, 66 assertions, 0 failures`

PASS delta: `+66` focused PASS lines in a new lane-scoped test file. `lane-status.json` `phpPass` moves from `49426` to `49492` for this isolated handoff. Mapped upstream coverage is unchanged because the slice reuses existing mapped LIKE/GLOB pattern, UTF-16 decode, affinity coercion, and current-source invalidation behavior rather than adding a new manifest row.

Non-overlap: avoids accepted Unicode GLOB range handling, UTF-16 malformed record guards, UTF-16 RTRIM/LIKE current-source handling, SELECT predicate affinity/collation execution, UTF-16 pattern-only next114 coverage, JSON/VFS/WAL/B-tree current-source clusters, and SELECT SQL executor clusters. The new surface is the composition of UTF-16 pattern/escape bytes with affinity range current/next invalidation and encoded range-bound provenance.

Dependency closure: no new support component is needed. This reuses lane-local UTF-16 encode/decode, SQLite LIKE/GLOB prefix ranges, affinity coercion, residual matching, and current/next cursor diagnostics.

Next task: continue encoding only on a non-overlapping collation, affinity, or malformed comparison edge with focused test growth; otherwise pivot to another current closure bucket.
