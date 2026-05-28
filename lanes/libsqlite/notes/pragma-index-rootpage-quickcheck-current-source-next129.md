# pragma-index-rootpage-quickcheck-current-source-next129

This slice adds `SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext129`, a
quick-check-specific current-source paginator for copied WordPress SQLite
images. It keeps `PRAGMA index_xinfo` rows tied to the same database/catalog
source hash, then appends the exact `PRAGMA quick_check` row stream, including
`quick_check(N)` limiting, cursor resume validation, attached-schema/table-valued
`pragma_index_xinfo(...)` dispatch, and rootpage enrichment when a quick-check
message names a known schema root page.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexRootpageQuickcheckCurrentSourceNext129Test.php
# 1 test files, 80 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-pragma-index-rootpage-quickcheck-current-source-next129.php --self-test
# wordpress-pragma-index-rootpage-quickcheck-current-source-next129 self-test passed
```

Non-overlap: this avoids accepted next103 mixed `index_xinfo` plus
quick/integrity root pagination and accepted next124 integrity index-rootpage
target-root rows. The new behavior is the exact `PRAGMA quick_check` row stream
and `quick_check(N)` limiter under a current-source cursor for index rootpage
triage.

Dependency closure: no new support component is needed. The slice reuses the
lane-local SQLite page-image parser, attached schema catalog,
`SQLitePragmaIntegrityCheck`, and current-source hashing already present under
`lanes/libsqlite`.
