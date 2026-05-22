# libsqlite WordPress Scenario

SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder, and b-tree page
header parser for schema/root pages.

## Example

`examples/wordpress-options-root-page.php` reads the first page of a
WordPress-oriented SQLite database file and reports the schema root page type,
cell count, content start, and fragmentation without using the PHP SQLite
extension. This is the first inspection primitive needed by import/export and
recovery tooling on hosts where `sqlite3` is unavailable.

## Next Task

Parse table leaf cells on the schema root page and decode the `sqlite_schema`
records needed to locate WordPress tables such as `wp_options`.
