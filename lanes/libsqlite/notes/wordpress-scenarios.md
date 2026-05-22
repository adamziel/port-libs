# libsqlite WordPress Scenario

SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder, b-tree page
header parser for schema/root pages, table leaf cell parsing, SQLite record
serial decoding, and `sqlite_schema` extraction for WordPress table discovery.

## Example

`examples/wordpress-options-root-page.php` reads the first page of a
WordPress-oriented SQLite database file and reports the schema root page type,
cell count, content start, and fragmentation without using the PHP SQLite
extension. This is the first inspection primitive needed by import/export and
recovery tooling on hosts where `sqlite3` is unavailable.

`examples/wordpress-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

## Next Task

Walk schema b-tree pages beyond the first leaf page and resolve root pages for
WordPress tables such as `wp_options` from real database files.
