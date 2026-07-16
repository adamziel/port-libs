# Foreign Key Deferred Enforcement Current

Slice: `foreign-key-deferred-enforcement-current`

Behavior: immediate `NO ACTION` foreign-key delete/update checks now fail at
the statement boundary when the FK is not deferred, while deferred `NO ACTION`
continues to report commit-time violations and unreferenced parent changes
remain allowed.

Red-first evidence before source fix:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeCorpusTest.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCorpusTest.php
2 test files, 88 assertions, 2 failures
```

Focused passing evidence after source fix:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCascadeCorpusTest.php lanes/libsqlite/tests/SQLiteForeignKeyOnUpdateCorpusTest.php lanes/libsqlite/tests/SQLiteForeignKeyDeferredCompositeRestrictNext10Test.php
3 test files, 128 assertions, 0 failures
```

Family check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteForeignKey*.php
9 test files, 452 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses the
existing bounded row-array FK executor and tightens current immediate/deferred
enforcement semantics.

Non-overlap: avoids suite-evidence/root metadata, PRAGMA catalog FK diagnostics,
trigger-recursive helper consolidation, and accepted cascade/RESTRICT behavior.
