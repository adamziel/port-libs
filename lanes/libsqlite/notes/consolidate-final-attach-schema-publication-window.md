# Attach schema publication-window consolidation

Session: `port-dev-sqlite-yield-consol-meth-attach-bf`
Micro-slice: `consolidate-final-numbered-methods-attach-schema-fifty-third-pass`

This slice removes one remaining numbered attach/temp WAL schema-cache test and
WordPress smoke surface. They are migrated to stable descriptive names:

- `SQLiteAttachTempWalSchemaCacheFinalPublicationWindowTest.php`
- `wordpress-attach-temp-wal-schema-cache-final-publication-window.php`

The behavior still exercises the canonical
`SQLiteAttachWalTempSchemaCachePlan::schemaCacheConsolidatedPlan()` production
entry point. The scenario data labels were scrubbed from generated `nextNNN`
suffixes to descriptive publication/cache names, while preserving the same
schema-cookie, attach/detach, WAL commit, index rename, table rename, and
statement reprepare assertions.

No new support component is needed; this reuses the existing attach WAL/temp
schema-cache implementation and focused TestRunner path.
