# UTF-16 NOCASE LIKE RTRIM Current Source Next213

## Behavior

This slice covers UTF-16 prepared `LIKE ... ESCAPE` patterns where the decoded
non-ASCII escape character escapes itself before an escaped wildcard literal.
The planner must decode the prepared bytes first, keep the escaped escape
literal in the RTRIM/NOCASE prefix, keep the escaped wildcard literal in the
next-source prefix, and invalidate stale current-source cursors when the
decoded prefix/token stream changes.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext213Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next213.php --self-test`
- Syntax checks: `php -l` on the new source, test, and example files
- Whitespace check: `git diff --check -- lanes/libsqlite`

## Non-overlap

Avoids the accepted next212 single Unicode ESCAPE normalization case, accepted
Unicode GLOB range behavior, malformed UTF-16 insert guards, JSON/B-tree/WAL
storage clusters, and SQL planner surfaces. No new dependency component is
needed; this reuses the existing native UTF-16 decoder, LIKE ESCAPE tokenizer,
ASCII NOCASE prefix range planner, RTRIM expression key handling, and
current-source invalidation diagnostics.
