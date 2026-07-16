# SQL Planner STAT4 Partial Covering Current Source Next90

Adds `SQLiteStat4PartialCoveringCurrentSourcePlan`, a bounded current-source
wrapper for STAT4-estimated partial covering index plans. It compares prepared
and current schema/stat4/projection metadata, forces reprepare when current
`sqlite_stat4` samples or covering projection metadata change, and preserves
the selected plan's partial predicate proof, covering source, ORDER BY mode,
and STAT4 current/next range evidence.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringCurrentSourceNext90Test.php`
- `php lanes/libsqlite/examples/application-planner-stat4-partial-covering-current-source-next90.php --self-test`

Application relevance: copied multisite `wp_options` plugin scans can reuse a
partial covering `(blog_id, option_name, autoload, option_value)` index only
when the prepared statement has current STAT4 distribution and projection
metadata. The smoke reports reprepare decisions and current STAT4 boundaries
for plugin import previews without requiring `ext/sqlite`.

Non-overlap: avoids accepted STAT4 partial-index current-source next88,
STAT4 expression range current-source next86, partial-index ORDER current-source
next85, expression-index range-cost ranking, OR partial covering plans, JSON
planner/table work, VFS/WAL/B-tree apply clusters, and accepted batch88 legacy
STAT4 sample compatibility. This slice only handles prepared-versus-current
source invalidation for STAT4 partial covering plans.

Dependency closure: no new support component is needed. The patch reuses native
PHP CREATE INDEX parsing, partial predicate proof, multicolumn range planning,
and STAT4 sample evidence helpers already present in the libsqlite lane.
