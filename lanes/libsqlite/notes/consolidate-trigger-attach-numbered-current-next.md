# Trigger/ATTACH Numbered CurrentNext Consolidation

Date: 2026-05-29

This slice consolidates the remaining numbered production classes in the trigger,
ATTACH/WAL/temp, and Application import family into stable unsuffixed class names.
The migrated direct tests and Application examples continue to exercise the same
savepoint, trigger RETURNING, FK/UPSERT, attach schema-cache, attach
transaction, and JSON schema import scenarios.

Production source scan used for this slice:

`rg -n "(Trigger|RowValue|AttachWalTemp|ApplicationImport).*Current(Source)?Next[0-9]+|Current(Source)?Next[0-9]+.*(Trigger|RowValue|AttachWalTemp|ApplicationImport)|Current(Source)?Next[0-9]+Signal|tagCurrentNext[0-9]+" lanes/libsqlite/src lanes/libsqlite/tests lanes/libsqlite/examples`

After consolidation, the assigned family scan returns no matches. A broader
source scan still reports `SQLiteAttachedSchemaCatalog::foreignKeyListCurrentSourceNext106()`,
which is a PRAGMA foreign-key helper outside this trigger/attach lane.

Dependency closure: no new support component is needed; this is a source-name
consolidation that reuses the existing focused PHP tests and Application examples.
