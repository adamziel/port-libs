# pragma-integrity-pointermap-foreignkey-current-source-next94

This slice fixes the current-source resume token for combined
`PRAGMA integrity_check` pointer-map diagnostics plus targeted
`foreign_key_check` rows. `SQLitePragmaIntegritySourceCursor` already emitted a
`next` token shaped as `['source_id' => ..., 'offset' => ...]`, but validation
only enforced the legacy `next_offset` key. A caller could therefore reuse the
right source token at the wrong offset and duplicate or skip preflight rows.

Behavior:

- emitted `next.offset` tokens are now validated against the requested page
  offset;
- legacy `next_offset` cursor tokens remain supported;
- source-only cursors still allow explicit manual seeks over the unchanged
  source;
- both table-valued `pragma_foreign_key_check(...)` and statement-form
  `PRAGMA foreign_key_check(...)` streams share the stricter offset guard.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapForeignKeyCurrentSourceNext94Test.php`
  - `1 test files, 40 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-integrity-pointermap-foreignkey-current-source-next94.php --self-test`
  - `application-pragma-integrity-pointermap-foreignkey-current-source-next94 self-test passed`
- PHP lint covered the changed source, new test, and new example.

Non-overlap:

This avoids accepted pointer-map/FK row collection, table-valued FK resolution,
autoindex/pointer-map diagnostics, PRAGMA integrity pagination, and batch90 FK
pointer-map row content. The new behavior is the current-source `next.offset`
resume guard for the existing combined stream.

Dependency closure:

No new support component is needed. The patch reuses existing native PHP
database hashing, PRAGMA integrity, pointer-map classification, attached schema
catalog, and foreign-key check primitives.
