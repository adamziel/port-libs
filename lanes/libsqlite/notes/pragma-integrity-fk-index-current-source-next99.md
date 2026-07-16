# pragma-integrity-fk-index-current-source-next99

This slice adds resumable current-source cursors for the combined
PRAGMA integrity/FK/index stream used by copied Application databases with
`main`, `temp`, and attached archive schemas.

Behavior:

- `SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield::pageWithSourceCursor()`
  returns a stable `source_id`, normalized source hashes, and a cursor for the
  next page.
- The source hash includes database bytes, attached catalog records/database
  list, schema FK/table rows, and the normalized integrity PRAGMA.
- Resume validation rejects stale database bytes, stale schemas, stale attached
  catalog/index metadata, stale integrity SQL, and mismatched offsets.
- Pagination preserves the existing mixed row order across index-admission,
  foreign-key violation, and integrity-check rows while retaining the existing
  blocker summary as `next_state`.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityFkIndexCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 70 assertions, 0 failures
```

PASS-line delta: 60 focused PHP PASS cases. `lane-status.json` `phpPass`
moves from 38278 to 38338. Mapped upstream coverage is unchanged because this
adds current-source behavior coverage without claiming a new upstream inventory
unit.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-integrity-fk-index-current-source-next99.php --self-test
application-pragma-integrity-fk-index-current-source-next99 self-test passed
```

Dependency closure: no new support component is needed. This reuses the
lane-local attached schema catalog, schema records, combined PRAGMA
integrity/FK/index stream, and existing integrity/FK/index primitives.

Non-overlap: avoids accepted next88 schema-preserving combined rows, next92
autoindex/FK current-source preflight, next90 pointer-map FK pagination, and
the accepted B-tree/VFS/WAL/storage clusters. The new surface is resumable
current-source validation for the combined PRAGMA integrity/FK/index stream.
