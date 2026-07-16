# SQLite UTF-16 NOCASE LIKE RTRIM Current Source Next173

## Slice

- Added `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` for UTF-16
  `rtrim(option_name) COLLATE NOCASE LIKE` current-source revalidation.
- The slice distinguishes byte-only decoded-text/trailing-space/encoding
  changes from semantic RTRIM key, NOCASE key, residual-result, candidate, and
  matched-rowset changes.
- It preserves SQLite-compatible ASCII-only `RTRIM` and `NOCASE` behavior:
  ASCII space is trimmed, while tab and NBSP remain residual false positives.

## Evidence

- Focused test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext173Test.php`
- Application smoke:
  `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next173.php`
- PHP lint and `git diff --check -- lanes/libsqlite` were run before handoff.

## Non-Overlap

This avoids accepted next157/162/165/166/167 behavior by not repeating byte-order
invalidations, prepared-pattern byte normalization, resume-token movement,
RTRIM ESCAPE normalization, or Unicode-prefix full-scan fallback alone. The new
behavior is the current-source byte-vs-semantic split for UTF-16 NOCASE LIKE
over RTRIM expression keys.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode,
ASCII-only RTRIM/NOCASE LIKE residual matching, and lane-local current-source
diagnostic planning.
