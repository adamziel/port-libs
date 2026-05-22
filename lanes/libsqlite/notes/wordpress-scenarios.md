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
finds the correct `sqlite_autoindex_wp_options_*` root page.

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
native index and rowid b-tree traversal.

`examples/wordpress-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

## Next Task

Port SQLite index b-tree comparison features that are still outside the current
slice: composite duplicate scans, expression indexes, partial indexes,
non-BINARY collations, descending sort order, and full composite-key range
scans.
