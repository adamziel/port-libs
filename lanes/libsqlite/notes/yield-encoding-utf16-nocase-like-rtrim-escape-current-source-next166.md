# UTF-16 NOCASE LIKE RTRIM ESCAPE Current-Source Next166

## Scope

- Adds `SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Plan` for
  `rtrim(option_name) COLLATE NOCASE LIKE rtrim(?) ESCAPE rtrim(?)`.
- Extends the accepted next163 RHS-pattern behavior with UTF-16 ESCAPE operand
  decode, ASCII-space-only RTRIM, single-character validation, byte-only
  reprepare diagnostics, and semantic current-source invalidation.
- Application smoke:
  `lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next166.php`.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNext166Test.php`
- Example smoke: `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-escape-current-source-next166.php --self-test`
- PHP lint and `git diff --check -- lanes/libsqlite` required before handoff.

## Non-Overlap

- Avoids accepted next163 UTF-16 NOCASE LIKE RTRIM RHS pattern coverage by
  adding ESCAPE operand normalization and semantic/byte-only current-source
  diagnostics.
- Does not repeat accepted Unicode GLOB ranges, malformed UTF-16 insert guard,
  UTF-16 GLOB affinity, or broader planner/VFS/WAL/B-tree surfaces.

## Dependency Closure

No new support component is needed. The slice reuses lane-local UTF-16
encoding/decoding, LIKE prefix planning, NOCASE residual matching, and
current-source cursor diagnostics.
