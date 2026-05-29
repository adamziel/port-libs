# sqlplanner-stat4-expression-partial-current-source-next177

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for bounded expression `BETWEEN` normalization over current-source STAT4 partial expression indexes.
- Focused behavior: `LOWER(option_name) BETWEEN 'plugin_cache' AND 'plugin_seo'` is expanded to inclusive range bounds, reparses stale prepared STAT4/source state, preserves lower/upper boundary rowids, and emits a cursor fence before covering column reads.
- WordPress smoke: `examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next177.php` previews copied `wp_options` plugin option scans after ANALYZE/stat4 changes without requiring ext/sqlite.
- Non-overlap: avoids accepted next164 explicit range terms, next169 full-vs-partial cost fencing, next170 next-source row churn admission, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters.
- Dependency closure: no new support component needed; this reuses existing native PHP current-source STAT4 expression partial planning.
