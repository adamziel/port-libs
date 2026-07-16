# SQL Planner STAT4 Expression Covering Current Source Next144

This slice adds `SQLitePlannerStat4ExpressionCoveringCurrentSourceNextPlan`, a current-source wrapper around the existing STAT4 expression-covering planner. It covers a stale prepared point lookup on `lower(option_name)` where `sqlite_stat4`, the schema cookie, and the covering index root changed after ANALYZE.

Focused behavior:

- Compares prepared and current STAT4 expression-covering row streams.
- Rejects stale prepared rowids and admits current rowids for the same point predicate.
- Preserves the covering cursor path, so `DeferredSeek` and table rowid lookup stay elided after the current-source recheck.
- Records current-source fence signatures for predicate, row stream, and covering payload.

Application relevance: copied `wp_options` import/cleanup queries often probe plugin option names through `lower(option_name)` while projecting `option_value` and metadata. This diagnostic keeps those point lookups current after ANALYZE without requiring ext/sqlite.

Non-overlap: this avoids accepted STAT4 expression IN next126, expression range next128, expression skip-scan next137, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, and PRAGMA clusters. No new support component is needed.
