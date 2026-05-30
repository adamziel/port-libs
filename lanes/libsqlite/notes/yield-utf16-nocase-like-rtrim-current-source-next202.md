# UTF-16 NOCASE LIKE RTRIM current-source next202

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, covering
`rtrim(option_name) COLLATE NOCASE LIKE (...)` when the LIKE RHS pattern is read
from a UTF-16 current/next source row rather than from a stable prepared
parameter.

Focused behavior:

- decodes current and next RHS source rows as UTF-8/UTF-16LE/UTF-16BE text;
- computes the current and next ASCII NOCASE LIKE prefix ranges after RTRIM;
- invalidates yielded cursors when the source rowid, source bytes, decoded
  pattern, range bounds, candidate rowset, matched rowset, false positives, or
  malformed row decoding changes;
- preserves the stable-source fast path when rowid, bytes, schema/source, and
  rowsets are unchanged.

Non-overlap:

- avoids accepted next191 prepared-pattern byte rebind diagnostics;
- avoids accepted next196 duplicate comparison-key peer resume;
- avoids next195 escaped literal-tail false-positive diagnostics;
- avoids Unicode GLOB range handling and malformed UTF-16 insert guards.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decoding, source-row pattern extraction, ASCII NOCASE LIKE prefix ranges,
RTRIM expression keys, and current-source diagnostics.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php
php -l lanes/libsqlite/examples/application-utf16-source-pattern-like-current-source-next202.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php
php lanes/libsqlite/examples/application-utf16-source-pattern-like-current-source-next202.php
git diff --check -- lanes/libsqlite
```
