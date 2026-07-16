# pragma-index-xinfo-integrity-current-source-next100

This slice adds `SQLitePragmaIndexXinfoIntegrityCurrentSourceYield`, a bounded
current-source wrapper over the accepted `index_xinfo` plus root integrity
yield. It keeps `PRAGMA index_xinfo` rows first, appends
`integrity_check`/`quick_check` root diagnostics, and annotates every row with
the launcher current source and next-slice source so Application import/repair
preflights can page through metadata and integrity blockers without losing
provenance.

Focused behavior:

- direct and table-valued `index_xinfo` forms preserve temp/main/attached
  current-source resolution;
- metadata rows are tagged as `metadata`, root diagnostics as
  `integrity_error`;
- appended integrity rows retain the current index target when one exists;
- page summaries expose `metadata_rows`, `integrity_errors`, and
  `index_root_integrity` blocking state;
- missing-index and valid-database paths remain empty/ready, while malformed
  root-page images stay blocked.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoIntegrityCurrentSourceYield.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoIntegrityCurrentSourceNext100Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-integrity-current-source-next100.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoIntegrityCurrentSourceNext100Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-integrity-current-source-next100.php --self-test
git diff --check -- lanes/libsqlite
```

Evidence:

- focused test: `1 test files, 82 assertions, 0 failures`, `70` PASS lines;
- Application smoke: `application-pragma-index-xinfo-integrity-current-source-next100 self-test passed`;
- expected dashboard movement: `phpPass` `38278 -> 38348`, mapped coverage
  `568 / 1589 -> 569 / 1589`.

Non-overlap:

This avoids accepted PRAGMA integrity pagination, `index_xinfo` expression
metadata, root-only next54 diagnostics, FK/index current-source pagination,
autoindex pointer-map checks, and batch94 PRAGMA pointer-map/foreign-key
integrity. The new surface is specifically current/next source tagging and
blocker summarization for combined `index_xinfo` plus root integrity rows.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP schema
catalog, attached-schema resolution, `PRAGMA index_xinfo`, and
`PRAGMA integrity_check` helpers.
