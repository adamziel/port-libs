# SQLite LIKE ESCAPE / GLOB Candidate Current-Source Next147

## Behavior

- Adds range-candidate reporting to `SQLiteEncodingCollationSourceCursor` so LIKE/GLOB index scans expose candidate rowids separately from residual matches.
- Threads candidate rowids, false positives, candidate encodings, candidate bytes, and candidate current/next change reasons through `SQLiteLikeGlobCurrentSourceNextPlan`.
- Fixes UTF-8 prefix upper-bound generation for valid non-ASCII LIKE/GLOB prefixes by advancing the last Unicode scalar instead of producing malformed byte-prefix bounds.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeEscapeGlobCandidateCurrentSourceNext147Test.php`
- Result: `1 test files, 56 assertions, 0 failures`.

## Application Smoke

- `php lanes/libsqlite/examples/application-like-escape-glob-candidates-current-source-next147.php --self-test`
- Result: `application-like-escape-glob-candidates-current-source-next147 self-test passed`.

## Non-Overlap

This avoids the accepted Unicode GLOB range, UTF-16 LIKE ESCAPE, UTF-16 malformed guard, and earlier LIKE/GLOB current-source slices. The new behavior is the candidate-vs-residual phase for ESCAPE and GLOB/collation scans plus Unicode prefix upper-bound safety for valid UTF-8 prefixes.

## Dependency Closure

No new support component is needed. The slice reuses the existing native encoding source cursor, LIKE/GLOB matcher, and current-source next statement reprepare planner.
