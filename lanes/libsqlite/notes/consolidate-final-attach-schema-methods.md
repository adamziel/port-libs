## Final Attach/Schema Method Consolidation

Date: 2026-05-29

Scope: `consolidate-final-numbered-methods-attach-schema`

Consolidated the final attach WAL/temp schema-cache generated public methods
into stable descriptive entrypoints:

- `finalSchemaCachePreparationWindow()`
- `finalSchemaCachePublishWindow()`
- `finalSchemaCacheAttachWindow()`

Direct tests and WordPress examples were renamed to stable descriptive filenames
and migrated to the unsuffixed entrypoints. Behavior, dependency evidence, and
assertion coverage are preserved; only the generated public method/file surface
was removed for this final attach/schema family slice.

Dependency closure: no new support component is needed. This is a source
consolidation of existing native PHP attach/schema cache behavior.
