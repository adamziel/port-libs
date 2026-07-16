# encoding-utf16-nocase-like-rtrim-current-source-next172

Behavior slice: UTF-16 `RTRIM(option_name) COLLATE NOCASE LIKE` current-source resume tokens now detect when the already-yielded `(key,rowid)` row moves after the saved token in the next source. The plan forces a reprepare from the range start instead of continuing and yielding the same Application option row twice.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext172Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 54 assertions, 0 failures
38 PASS lines
```

Example smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next172.php --self-test
application-utf16-nocase-like-rtrim-current-source-next172 self-test passed
```

Non-overlap: this does not repeat the accepted next166 UTF-16 RTRIM ESCAPE byte/semantic reprepare handling, next165 general resume-token rowset checks, Unicode GLOB ranges, malformed UTF-16 insert guards, or accepted LIKE/GLOB predicate metadata. It adds the narrower yielded-token duplicate prevention edge for a rowid whose RTRIM/NOCASE key moves after the saved token.

Dependency closure: no new support component is needed; the slice reuses native UTF-16 decode, LIKE prefix planning, ASCII NOCASE, RTRIM expression keys, and current-source diagnostics already present in the libsqlite lane.
