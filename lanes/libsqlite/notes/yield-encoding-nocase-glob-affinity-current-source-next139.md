# encoding nocase glob affinity current source next139

## Behavior

- Adds `SQLiteNocaseGlobAffinityCurrentSourceNextPlan` for the SQLite rule that `GLOB` remains bytewise/case-sensitive even when the available option-name source is ordered by `NOCASE`.
- A fixed `GLOB` prefix is still recorded, but the range is only reusable for `BINARY` collation. `NOCASE` sources fall back to a residual scan and report `glob-range-requires-binary-collation`.
- Tracks text-affinity coercion, encoded bytes, candidate rowsets, matched rowsets, and current-source invalidation across `current` and `next` wp_options snapshots.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseGlobAffinityCurrentSourceNext139Test.php`
- Example smoke: `php lanes/libsqlite/examples/wordpress-nocase-glob-affinity-current-source-next139.php --self-test`
- PHP lint and `git diff --check -- lanes/libsqlite` were run before handoff.

## Non-overlap

This slice avoids accepted Unicode GLOB range handling, UTF-16 malformed guards, LIKE/RTRIM behavior, select predicate affinity metadata, and prior `SQLiteDatabase::globMatches()` character-class work. It covers the separate planner/source decision for a `NOCASE` current source that must not use the bytewise `GLOB` prefix range.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP pattern matching, affinity comparison, and current-source row-array planning helpers.
