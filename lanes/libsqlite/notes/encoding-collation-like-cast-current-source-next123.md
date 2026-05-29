# encoding-collation-like-cast-current-source-next123

Status: focused PHP behavior growth for current/next `CAST(...)` results feeding LIKE/GLOB residual checks and BINARY/NOCASE/RTRIM collation keys.

This slice adds `SQLiteCastCollationLikeCurrentSourceNextPlan::wordpressOptionValueCastScan()`. It runs `CAST(option_value AS ...)` through the bounded parser-level `SQLiteSelectSql` executor, records cast storage and text bytes, applies LIKE/GLOB residual matching, records collation keys, and compares current/next `wp_options` sources for changed cast results, entered/exited matches, matched text changes, and schema-cookie invalidation.

WordPress path: `wordpress-cast-collation-like-current-source-next123.php` models a copied `wp_options` source where plugin option values change type/text after an import, so a prepared cast/pattern cursor must be invalidated before reuse.

Non-overlap: avoids accepted Unicode GLOB ranges, malformed UTF-16 record guards, UTF-16 LIKE/GLOB current-source cursors, numeric-affinity current-source ranges, LIKE/GLOB predicate coercion, SELECT SQL subquery/GROUP/ORDER/LIMIT clusters, JSON table source/cursor/constraint work, and VFS/WAL/B-tree application clusters. The new surface is current/next source tracking for parser-level CAST expression results before LIKE/GLOB residual and collation comparison.

Dependency closure: no new support component is needed. The slice reuses native PHP SELECT SQL parsing, CAST expression execution, SQLite LIKE/GLOB matchers, BLOB values, and built-in collation rules.

Expected dashboard movement: `phpPass +65`; mapped upstream coverage unchanged because this adds focused PHP behavior over already mapped cast/affinity/collation inventory.
