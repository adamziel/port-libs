# VDBE Sorter DISTINCT Collation Current/Next 27

This slice adds a focused current-source test cluster for `SQLiteVdbeAggregateDistinctCursor` over Application-style `wp_options` rows.

Behavior covered:

- DISTINCT cursor `current()`, `currentKey()`, `currentValue()`, `currentRow()`, `next()`, `rewind()`, `remaining()`, and EOF behavior after sorter scans.
- BINARY, NOCASE, RTRIM, and NOCASE collation equality over DISTINCT aggregate keys.
- Numeric, integer, text, and none-affinity key comparison when duplicate aggregate keys mix integers, reals, and numeric text.
- Composite DISTINCT keys where site/bucket dimensions reset duplicate handling.
- FILTER-style truthiness before DISTINCT selection.
- Application smoke `application-vdbe-sorter-distinct-collation.php` for option-name and byte-size aggregate distinct diagnostics without ext/sqlite.

Non-overlap:

- Avoids accepted VDBE aggregate ORDER BY cursoring, VDBE aggregate DISTINCT cursor batch19 basics, sorter NULL/collation order-only cases, JSON table/source/cursor work, VFS/WAL/B-tree application clusters, and current accepted SELECT SQL expression/group/subquery clusters.

Dependency closure:

- No new dependency component is needed. The slice reuses existing native PHP VDBE sorter comparison and aggregate cursor helpers.
