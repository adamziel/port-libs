2026-05-27 - JSON type affinity path edge next10

- Behavior: parser-level `json_type()`, `json_array_length()`, `json_extract()`, and `jsonb_extract()` now apply SQLite-style text affinity to JSON path operands in SELECT expressions. BLOB paths such as `CAST('$.plugin.priority' AS BLOB)` resolve normally, scalar non-path operands are converted before malformed-path validation, and SQL NULL path operands return NULL.
- Focused test delta: `+25` TestRunner PASS cases in `SQLiteJsonTypeAffinityPathEdgeTest.php`; focused output reported `1 test files, 26 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-json-type-affinity-path-edge.php` reports copied `wp_options` diagnostics where JSON path values are stored with BLOB affinity and used by parser-level `json_type()` / `json_extract()` without requiring `ext/sqlite`.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint clusters, accepted JSON host joins, accepted JSON negative-path execution, accepted SQL expression ORDER BY, accepted Unicode GLOB, accepted VFS/WAL/B-tree storage clusters, and the batch6/7 JSON aggregate window coverage.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteSelectExpression`, JSON inspection/extract helpers, and bounded SELECT SQL row-array execution.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpression.php

php -l lanes/libsqlite/tests/SQLiteJsonTypeAffinityPathEdgeTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTypeAffinityPathEdgeTest.php

php -l lanes/libsqlite/examples/application-json-type-affinity-path-edge.php
No syntax errors detected in lanes/libsqlite/examples/application-json-type-affinity-path-edge.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTypeAffinityPathEdgeTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS applies blob path affinity to json_type literals
PASS applies blob path affinity to json_array_length literals
PASS applies blob path affinity to json_extract literals
PASS applies blob path affinity to jsonb_extract literals
PASS applies row blob path affinity to json_type
PASS applies row blob path affinity to json_extract
PASS applies concatenated blob path affinity to json_type
PASS applies concatenated blob path affinity to json_extract
PASS applies cast integer text path affinity before path validation
PASS applies cast real text path affinity before path validation
PASS returns null for json_type null path operands
PASS returns null for json_array_length null path operands
PASS returns null for json_extract null path operands
PASS returns null for json_extract mixed path list with null path
PASS uses blob path affinity in where json_type predicates
PASS uses blob path affinity in where json_extract numeric predicates
PASS uses blob path affinity in order by json_extract expressions
PASS uses row text paths without regressing ordinary text affinity
PASS uses quoted blob paths for object labels with spaces
PASS uses blob paths for dotted quoted labels
PASS uses blob path affinity for reverse array index extraction
PASS uses blob path affinity for json_type reverse array index
PASS keeps missing blob paths as null results
PASS rejects malformed blob path bytes after affinity conversion
PASS rejects array-valued path operands without scalar affinity

1 test files, 26 assertions, 0 failures

php lanes/libsqlite/examples/application-json-type-affinity-path-edge.php
passed; emitted copied wp_options BLOB-path diagnostics for plugin_seo and plugin_forms.

git diff --check -- lanes/libsqlite
passed
```
