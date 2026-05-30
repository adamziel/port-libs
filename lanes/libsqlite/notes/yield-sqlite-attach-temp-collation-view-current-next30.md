# yield-sqlite-attach-temp-collation-view-current-next30

Implemented a bounded attached/temp view analyzer for current-source view bodies with `COLLATE` clauses. The slice covers temp-view resolution across `temp`, `main`, and attached schemas, persistent-view same-schema enforcement, view-column extraction, source binding, collation dependency collection, missing-collation diagnostics, and a Application smoke for copied `wp_options` temp views joining an attached site database.

Non-overlap: this does not repeat accepted ATTACH temp/VFS open planning, temp view trigger resolution, JSON table SELECT sources, SQL expression `ORDER BY`, grouped SELECT text, VFS writer/sync/lock work, or accepted B-tree/WAL storage clusters. It is a narrower view-body current-source/collation dependency check for attached catalogs.

Dependency closure: no new support component is needed. The slice reuses the existing lane-local `SQLiteAttachedSchemaCatalog` and `SQLiteSchemaRecord` abstractions; activation evidence is the focused PHP test plus Application smoke output.
