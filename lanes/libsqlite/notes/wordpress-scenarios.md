# libsqlite WordPress Scenario

SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder, b-tree page
header parser for schema/root pages, table leaf and table interior cell
parsing, a page-backed database reader, SQLite record serial decoding, and
`sqlite_schema` table-b-tree traversal for WordPress table discovery. The
current slice also decodes bounded table rows and maps the standard
`wp_options` row shape into `option_id`, `option_name`, `option_value`, and
`autoload` fields without using the PHP SQLite extension. Large
`option_value` records that spill from a table leaf cell into SQLite overflow
pages are now reassembled through the native page reader. Explicit
`CREATE INDEX ... ON wp_options(option_name)` b-trees can now be parsed and
used to fetch a single option by indexed name, then resolve the stored rowid
through the table b-tree without scanning the whole options table. The same
lookup path now handles automatic `UNIQUE` indexes where SQLite records
`sqlite_autoindex_*` schema rows with `sql` set to `NULL`, by inferring the
first indexed column from the owning table's `CREATE TABLE` statement. It also
handles automatic non-rowid `PRIMARY KEY` indexes, preserving earlier UNIQUE
autoindex slots so a WordPress-shaped `PRIMARY KEY(option_name)` lookup still
finds the correct `sqlite_autoindex_wp_options_*` root page. Explicit
`CREATE INDEX` definitions now carry first-column `COLLATE` and `ASC`/`DESC`
metadata into lookup, so a descending `option_name COLLATE NOCASE` index can
serve case-insensitive option recovery. Partial `option_name` indexes are
detected and skipped for unconstrained lookup instead of returning incomplete
results; the safe `WHERE option_name IS NOT NULL` partial-index form is usable
for non-null option-name point lookup. Non-unique first-column indexes can now
be scanned for duplicate matches, allowing a `wp_options(autoload,
option_name)` index to return all autoloaded options for a requested value.
Explicit composite index metadata is now parsed far enough to constrain both
`autoload` and `option_name`, including second-column `NOCASE` comparison and
safe `autoload IS NOT NULL` partial-index use for a known non-null value.
Explicit or safe partial `wp_options(option_name)` indexes can also serve
bounded range scans, including open lower/upper bounds and inclusive upper
bounds. Bounded range scans skip `NULL` option-name keys the same way SQL
comparison predicates do, which lets recovery tooling inspect transient-style
or migration-prefix option-name ranges without decoding every row in the
options table. Equality partial indexes such as
`CREATE INDEX ... ON wp_options(option_name) WHERE autoload='yes'` are now
usable when the recovery caller supplies the matching autoload constraint, so
autoloaded single-option lookups can avoid both a whole-table scan and a wider
composite index requirement. OR equality partial predicates such as
`WHERE autoload='yes' OR autoload='on'` are also usable when the caller
supplies one matching autoload value, which helps migration/recovery tools read
WordPress databases with mixed legacy autoload state encodings.

## Example

`examples/wordpress-options-root-page.php` reads a WordPress-oriented SQLite
database file, walks the `sqlite_schema` table b-tree, resolves the
`wp_options` root page, reports schema/options root-page metadata, and emits a
bounded sample of decoded `wp_options` records without using the PHP SQLite
extension. The same path now handles large serialized/autoloaded option values
stored on overflow pages. This is an inspection primitive needed by
import/export and recovery tooling on hosts where `sqlite3` is unavailable.

`examples/wordpress-indexed-option-lookup.php` reads a WordPress-oriented
SQLite database file, resolves an explicit `wp_options(option_name)` index,
an automatic `UNIQUE` option-name autoindex, or an automatic non-rowid
`PRIMARY KEY` option-name autoindex, and returns one option by name using
native index and rowid b-tree traversal. Explicit first-column
`COLLATE NOCASE`, `COLLATE RTRIM`, and `DESC` index metadata are honored for
point lookups. Unsupported partial indexes are not used for unconstrained
option lookup, while `WHERE option_name IS NOT NULL` indexes can serve normal
non-null option-name recovery.

`examples/wordpress-autoloaded-options.php` reads a WordPress-oriented SQLite
database file, resolves an explicit or safe partial first-column
`wp_options(autoload, ...)` index, and returns all matching options for an
autoload value without scanning the entire `wp_options` table. This maps the
recovery/import use case where a site needs to inspect autoloaded options on a
host without the PHP SQLite extension.

`examples/wordpress-autoloaded-option-by-name.php` reads a WordPress-oriented
SQLite database file, resolves either an explicit composite
`wp_options(autoload, option_name)` index or an equality partial
`wp_options(option_name) WHERE autoload='yes'` index. The same path now accepts
OR equality partial predicates such as `autoload='yes' OR autoload='on'` when
the requested autoload value matches one branch, and returns a single option
when both the autoload value and option name are known. This is useful for
recovery tools that need to inspect one autoloaded option while avoiding a
whole-table scan on constrained hosts.

`examples/wordpress-option-name-range.php` reads a WordPress-oriented SQLite
database file, resolves an explicit or safe partial `wp_options(option_name)`
range index, and returns options whose names fall between caller-supplied lower
and upper bounds. Either bound can be omitted with `-`, and the upper bound can
be made inclusive; at least one bound is required. By default it targets the
`_transient_` prefix range, which maps cleanup and cache-inspection workflows
on hosts without the PHP SQLite extension.

`examples/wordpress-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

## Next Task

Port SQLite index b-tree comparison features that are still outside the current
slice: optimized b-tree seek bounds, expression indexes, comparison/range/AND
partial-index predicate implication, custom collations, automatic-index
collation metadata, and full composite-key range scans.
