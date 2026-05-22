# libsqlite WordPress Scenario

SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder, b-tree page
header parser for schema/root pages, table leaf and table interior cell
parsing, a page-backed database reader, SQLite record serial decoding, and
`sqlite_schema` table-b-tree traversal for WordPress table discovery.

## Example

`examples/wordpress-options-root-page.php` reads a WordPress-oriented SQLite
database file, walks the `sqlite_schema` table b-tree, resolves the
`wp_options` root page, and reports both schema and options root-page metadata
without using the PHP SQLite extension. This is an inspection primitive needed
by import/export and recovery tooling on hosts where `sqlite3` is unavailable.

`examples/wordpress-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

## Next Task

Read bounded `wp_options` table leaf rows from resolved root pages, including
`option_name` and `option_value` records. Overflow payloads remain explicitly
unsupported until that SQLite file-format slice is ported.
