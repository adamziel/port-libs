# real-upstream-corpus-pragma-schema-dynamic-20260530T192344Z-1

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

Implemented a non-overlapping real upstream pragma/schema corpus batch from
SQLite upstream `test/pragma5.test`:

- `pragma5-1.0`: `PRAGMA table_info(pragma_function_list)`
- `pragma5-1.1`: built-in `upper` row in `pragma_function_list`
- `pragma5-1.2`: application-defined external functions in `pragma_function_list`
- `pragma5-2.0`: `PRAGMA table_info(pragma_module_list)`
- `pragma5-2.1`: `fts5` and application modules in `pragma_module_list`
- `pragma5-3.0`: `PRAGMA table_info(pragma_pragma_list)`
- `pragma5-3.1`: `pragma_list` discovery in `pragma_pragma_list`

The PHP batch exercises 170 distinct dynamic application inventories through
`SQLitePragmaSchemaCatalog`, covering function type/encoding/arity/flag
normalization, builtin-vs-application function rows, module sorting, collation
sequence preservation, virtual PRAGMA table schemas, and table-valued PRAGMA
parser dispatch.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionBatchTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2040 assertions, 0 failures
```

Expected lane-local movement: `+2040` focused TestRunner PASS lines, from
`389246` to `391286` pass / `0` fail. Mapped coverage is unchanged at
`1472 / 1589` because this is PASS-line growth over an already mapped
upstream PRAGMA script family.

Dependency closure: no new support component is needed. The batch reuses the
existing PHP `SQLitePragmaSchemaCatalog` virtual PRAGMA catalog support.

Non-overlap: this does not repeat the accepted pragma schema dynamic table,
index, foreign-key, schema2/schema3, wide-batch, or thousand-row batches. It
targets the separate upstream `pragma5.test` virtual introspection-table
surface.
