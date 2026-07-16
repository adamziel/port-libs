# encoding-utf16-rtrim-like-pattern-current-source-next138

Status: focused PHP behavior growth for UTF-16 encoded LIKE patterns and
ESCAPE bytes on copied Application `wp_options.option_name` scans using an RTRIM
collation.

Behavior:

- Adds `SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan`.
- Decodes UTF-8/UTF-16LE/UTF-16BE pattern and optional ESCAPE bytes before
  applying SQLite LIKE matching.
- Preserves current RTRIM behavior: the RTRIM index range remains unusable for
  LIKE, so the scan is residual/full-scan and LIKE does not trim trailing
  spaces.
- Tracks current/next invalidation by source, malformed row text, row text,
  text encoding, encoded bytes, RTRIM key, matched rowset, and rejected
  residual rowset.

Evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikePatternCurrentSourceNext138Test.php
php lanes/libsqlite/examples/application-utf16-rtrim-like-pattern-current-source-next138.php --self-test
php -l lanes/libsqlite/src/SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteUtf16RtrimLikePatternCurrentSourceNext138Test.php
php -l lanes/libsqlite/examples/application-utf16-rtrim-like-pattern-current-source-next138.php
git diff --check -- lanes/libsqlite
```

Expected dashboard delta:

- `phpPass`: +73 from the new focused test file once accepted.
- `benchmarkDenominator.mapped`: unchanged; this composes already mapped
  UTF-16 decoding, LIKE matching, RTRIM collation, and current-source cursor
  behavior rather than claiming a new upstream inventory unit.

Non-overlap:

This avoids accepted Unicode GLOB ranges, UTF-16 malformed record guards,
UTF-16 row-text RTRIM LIKE with string patterns, UTF-16 pattern decoding for
non-RTRIM scans, CAST RTRIM LIKE/GLOB ranges, NOCASE/RTRIM LIKE switching,
RTRIM/NOCASE/GLOB batch135 behavior, JSON table/source/constraint work,
SELECT SQL text/order/group/subquery clusters, VFS writer/sync/lock/rollback
clusters, WAL checkpoint/savepoint clusters, and B-tree page/freelist/overflow
clusters. The new surface is only UTF-16 encoded pattern and ESCAPE decoding
inside the RTRIM LIKE residual current-source path.

Dependency closure:

No new support component is needed. This reuses lane-local UTF-16 text
encoding/decoding, LIKE matching, RTRIM collation keys, and current-source
diagnostics.
