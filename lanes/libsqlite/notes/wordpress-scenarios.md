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
finds the correct `sqlite_autoindex_wp_options_*` root page. Automatic indexes
now inherit first-column `COLLATE` and `DESC` metadata from `CREATE TABLE`
constraints, so a WordPress-shaped `UNIQUE(option_name COLLATE NOCASE DESC)`
autoindex can serve case-insensitive option recovery. Explicit `CREATE INDEX`
definitions also carry first-column `COLLATE` and `ASC`/`DESC` metadata into
lookup, so a descending `option_name COLLATE NOCASE` index can serve the same
recovery path. Partial `option_name` indexes are detected and skipped for
unconstrained lookup instead of returning incomplete results; the safe
`WHERE option_name IS NOT NULL` partial-index form is usable for non-null
option-name point lookup. Non-unique first-column indexes can now be scanned
for duplicate matches, allowing a `wp_options(autoload,
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
WordPress databases with mixed legacy autoload state encodings. AND-connected
partial predicates such as
`WHERE autoload='yes' AND option_name IS NOT NULL` are now accepted only when
every term is implied by caller-supplied constraints, so narrowed autoloaded
option indexes can be used without risking incomplete generic lookups.
Comparison and `BETWEEN` partial predicates are now parsed for bounded
`option_name` point and range lookups, so a transient-specific partial index
such as
``WHERE option_name >= '_transient_' AND option_name < '_transient`'``
can serve recovery scans only when the requested bounds or option name are
contained by that predicate.
First-term `lower(option_name)` expression indexes are now parsed as expression
indexes rather than plain column indexes. A case-folded recovery lookup can use
the stored lowered key payload to find `wp_options` rows such as `SiteURL`
without requiring the PHP SQLite extension, while generic `option_name` lookup
continues to reject expression-only indexes unless the caller asks for the
lowercase expression path. The same expression-index path can now serve
case-folded option-name range scans, so transient or migration-prefix recovery
can match mixed-case option rows through `lower(option_name)` while avoiding
ordinary `option_name` index assumptions. Only safe `option_name IS NOT NULL`
partial predicates are accepted for expression ranges; raw comparison
predicates are left unsupported because they are not implied by folded bounds.
The lower-expression path now also supports bounded `IN (...)` reads. Recovery
or preload tools can request a small mixed-case list such as `SITEURL,HOME`
through `wp_options(lower(option_name))`, avoid duplicate rows for duplicate
RHS names, ignore `NULL` RHS terms, and skip out-of-range index branches before
page decoding when a large or partially damaged options database contains
unrelated lower-key subtrees.
First-term `substr(option_name,start,length)` expression indexes are now
parsed for non-zero integer start and optional non-negative length literals. A
WordPress recovery tool can use a `substr(option_name,1,N)` expression index to read prefix
buckets such as `_transient_` through native index traversal, including
`COLLATE NOCASE` comparison and safe `option_name IS NOT NULL` partial
predicate checks. This remains intentionally narrower than SQLite's full
expression engine: variable-start substrings, expression `IN` lookup families
beyond `lower(column)` and this literal-start prefix-list path, and arbitrary
functions are still future slices.
The literal-start prefix path now also supports bounded `IN (...)` reads for
same-length prefixes. Recovery tools can read `_transient_` and `_site_trans`
cache buckets from one `substr(option_name,1,N)` expression index, avoid
duplicate rows for duplicate RHS prefixes, ignore `NULL` RHS values, and skip
out-of-range expression-index branches before page decoding.
Negative literal starts are now
accepted for suffix buckets such as `substr(option_name,-9)`: native recovery
tools can inspect `*_settings` option groups through stored suffix keys,
including `COLLATE NOCASE`/`DESC` metadata, without treating that expression
index as a normal `option_name` column index.
First-term `length(option_name)` expression indexes are now parsed for exact
integer length bucket lookups. A WordPress audit or recovery tool can use a
`length(option_name)` index to find suspiciously short, policy-sensitive, or
known-length option-name groups without scanning the whole `wp_options` table.
This slice accepts only safe `option_name IS NOT NULL` partial predicates and
uses UTF-8 character length when text is decodable, matching SQLite's text
length semantics for the current WordPress-oriented fixture boundary.
The same length-expression path now supports bounded `IN (...)` reads for
multiple integer buckets. Recovery and audit tools can request lengths such as
`4,10` in one index pass, ignore `NULL` RHS values, reject invalid length
terms before lookup, and skip unrelated length subtrees before page decoding.
First-term `CAST(option_value AS INTEGER)` expression indexes are now parsed
for exact integer lookups. Recovery and audit tools can find numeric-looking
option values such as `db_version` through SQLite's integer cast behavior,
including text prefixes like `58796abc` and non-numeric text casting to `0`,
without treating the expression index as a normal `option_value` column index.
This slice accepts only safe `option_value IS NOT NULL` partial predicates.
The same CAST-expression path now supports bounded `IN (...)` reads for
multiple integer buckets. Recovery and audit tools can request values such as
`58796,0` in one index pass, ignore `NULL` RHS values, reject invalid
non-integer terms before lookup, suppress duplicate RHS output, and skip
unrelated integer-key subtrees before page decoding.
The CAST-expression path also supports bounded integer range scans with open
or inclusive upper bounds. Recovery and audit tools can inspect numeric option
families such as version counters or plugin migration markers through
`CAST(option_value AS INTEGER) >= 100 AND < 60000`, while still using SQLite's
text-prefix integer cast rules and avoiding unrelated index branches.
Composite `wp_options(autoload, option_name)` indexes can now serve the common
SQLite equality-prefix plus range shape: `autoload='no'` constrains the first
indexed column while bounded `option_name` comparisons scan only matching
index records. This maps transient cleanup and cache-inspection workflows that
need non-autoloaded `_transient_` rows from a database image. The same path
honors second-column `NOCASE` comparison, physical `DESC` index order, and
partial predicates such as `autoload='no' AND option_name IS NOT NULL` only
when the caller's constraints imply the predicate.
The composite range path now also prunes unrelated b-tree branches before
reading their pages, so a recovery/import tool can still inspect a narrow
autoload/name range when an out-of-range index branch is damaged or expensive
to hydrate.

First-column `IN (...)` option-name lookups now read multiple requested
options through an `option_name` index, suppress duplicate RHS names the way
SQLite avoids duplicate result rows, and ignore `NULL` RHS values for `WHERE`
matching. The same path can safely use `WHERE option_name IS NOT NULL` partial
indexes and exact-order `WHERE option_name IN ('siteurl','home')` partial
indexes, matching the bounded SQLite planner behavior instead of treating every
logical subset as usable. IN-list reads now also prune out-of-range index
subtrees before page decoding, so a small preload list can still be recovered
when an unrelated branch of a large `wp_options(option_name)` index is damaged
or expensive to hydrate.

First-column range, lower-expression IN-list/range, length-expression IN-list,
CAST-expression IN-list/range, first-column IN-list, and composite
equality-prefix range scans now use bounded index b-tree traversal instead of
decoding every index page. This matters for WordPress recovery and import tools
that inspect a narrow option-name range or a small known option-name set from a
large or partially damaged database image: an unrelated out-of-range index
branch no longer has to be readable before constrained `wp_options(option_name)`,
`wp_options(lower(option_name))`, `wp_options(CAST(option_value AS INTEGER))`,
or `wp_options(autoload, option_name)` lookups can return matching rows.

The reader now also exposes `sqlite_sequence` records for AUTOINCREMENT tables.
WordPress import, recovery, or Data Liberation tooling can inspect sequence
counters for tables such as `wp_posts`, `wp_comments`, and `wp_users` from a
raw database image, preserving mutable SQLite `name` and `seq` scalar values
instead of assuming every `seq` cell is an integer.
The native AUTOINCREMENT state can now also compute the next generated ID from
the target table plus `sqlite_sequence`, create a missing sequence row in
state, recover from invalid mutable `seq` values, and advance the counter for
explicitly imported WordPress IDs so the next generated post/comment/user ID
does not collide with imported content. This is deliberately a bounded
read/write model for sequence state, not a general SQL insert engine or raw
SQLite page writer.

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
native index and rowid b-tree traversal. Explicit and automatic first-column
`COLLATE NOCASE`, `COLLATE RTRIM`, and `DESC` index metadata are honored for
point lookups. Unsupported partial indexes are not used for unconstrained
option lookup, while `WHERE option_name IS NOT NULL` indexes can serve normal
non-null option-name recovery.

`examples/wordpress-options-by-name-list.php` reads a WordPress-oriented SQLite
database file, resolves an indexed `wp_options(option_name)` IN-list lookup,
and returns a bounded set of named options such as `siteurl,home,blogname`
without scanning the full options table or using the PHP SQLite extension. This
path now uses bounded index traversal, mapping plugin/theme preload and
recovery workflows that need a small known set of options from a database image
without requiring every unrelated index branch to be readable first.

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
the requested autoload value matches one branch, and AND-connected partial
predicates such as `autoload='yes' AND option_name IS NOT NULL` when all terms
are implied. It returns a single option when both the autoload value and option
name are known. This is useful for recovery tools that need to inspect one
autoloaded option while avoiding a whole-table scan on constrained hosts.

`examples/wordpress-option-name-range.php` reads a WordPress-oriented SQLite
database file, resolves an explicit or safe partial `wp_options(option_name)`
range index, and returns options whose names fall between caller-supplied lower
and upper bounds. The range helper now also accepts comparison and `BETWEEN`
partial indexes when the requested bounds imply the partial predicate. Either
bound can be omitted with `-`, and the upper bound can be made inclusive; at
least one bound is required. By default it targets the `_transient_` prefix
range, which maps cleanup and cache-inspection workflows on hosts without the
PHP SQLite extension.

`examples/wordpress-autoloaded-option-name-range.php` reads a
WordPress-oriented SQLite database file, resolves a composite
`wp_options(autoload, option_name)` index, and returns options for one autoload
value whose names fall between caller-supplied bounds. By default it targets
non-autoloaded `_transient_` rows, which maps transient cleanup and recovery
tools that need SQLite index semantics without the PHP SQLite extension.

`examples/wordpress-lowercase-option-lookup.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns a single option
by case-folded name. This maps recovery workflows that need case-insensitive
option inspection from a database image but must not treat expression indexes
as ordinary column indexes.

`examples/wordpress-lowercase-option-name-range.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns options whose
folded names fall between caller-supplied bounds. By default it targets the
`_transient_` prefix range, mapping case-folded transient cleanup and recovery
without requiring the PHP SQLite extension or every out-of-range index branch
to be readable.

`examples/wordpress-lowercase-options-by-name-list.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns a bounded set of
case-folded names such as `SITEURL,HOME` without scanning the whole table. This
maps plugin/theme preload and recovery workflows where option names may have
unexpected case and a plain `option_name` index is not available.

`examples/wordpress-option-name-prefix.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(substr(option_name,1,N))` expression index, and returns options
whose name prefix equals the caller-supplied prefix. By default it targets the
`_transient_` bucket, mapping cache/transient inspection from SQLite database
images without requiring the PHP SQLite extension or a full table scan.

`examples/wordpress-option-name-prefix-list.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(substr(option_name,1,N))` expression index, and returns options
whose prefix is in a same-length caller-supplied list such as
`_transient_,_site_trans`. This maps cache/site-transient recovery and preload
workflows that need multiple option-name buckets without scanning every row.

`examples/wordpress-option-name-suffix.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(substr(option_name,-N))` expression index, and returns options
whose name suffix equals the caller-supplied suffix. By default it targets
`_settings`, mapping plugin/theme settings bucket inspection from database
images without requiring the PHP SQLite extension or a full table scan.

`examples/wordpress-option-name-length.php` reads a WordPress-oriented SQLite
database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose names have the requested length.
By default it targets length `4`, mapping quick recovery checks for compact
core options such as `home` or other policy-sensitive option-name buckets
without requiring a full table scan.

`examples/wordpress-option-name-length-list.php` reads a WordPress-oriented
SQLite database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose name lengths are in a caller
supplied list such as `4,10`. This maps multi-bucket option-name audits and
preload checks without scanning every `wp_options` row.

`examples/wordpress-option-value-integer.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose values cast to a requested integer. This maps recovery and audit
checks for numeric-looking options such as `db_version`, plugin counters, or
legacy values like `58796abc` that SQLite casts by their leading integer text
without requiring the PHP SQLite extension.

`examples/wordpress-option-value-integer-list.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose cast values are in a caller supplied integer list such as
`58796,0`. This maps multi-value numeric option audits and recovery checks
without scanning every `wp_options` row.

`examples/wordpress-option-value-integer-range.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(CAST(option_value AS INTEGER))` expression index, and returns
options whose cast values are inside caller supplied integer bounds. This maps
version/counter audits and recovery checks that need numeric ranges without
scanning every `wp_options` row.

`examples/wordpress-sequence-counters.php` reads a WordPress-oriented SQLite
database file, resolves the internal `sqlite_sequence` table, and reports all
AUTOINCREMENT rows plus selected counters such as `wp_posts`, `wp_comments`,
and `wp_users`. This maps ID-continuity checks during imports and recovery on
hosts where the PHP SQLite extension is unavailable.

`examples/wordpress-autoincrement-continuity.php` reads a WordPress-oriented
SQLite database file, builds AUTOINCREMENT state for selected tables, reports
the next generated ID and sequence row after a generated insert, and can model
planned explicit imports such as `wp_posts=500` to verify that subsequent
generated IDs continue after imported content.

`examples/wordpress-schema-record.php` builds a deterministic schema-root page
containing a `wp_options` table record, parses the table leaf cell payload, and
reports the decoded table name/root page without using the PHP SQLite extension.

## Next Task

Port SQLite index b-tree comparison features that are still outside the current
slice: expression indexes beyond `lower(column)`, literal-start
`substr(column,...)`, `length(column)`, and `CAST(column AS INTEGER)`
point/list/range buckets; broader expression `IN (...)` lookup families beyond
`lower(column)` and literal-start `substr(column,1,N)` plus `length(column)`
and `CAST(column AS INTEGER)` buckets; custom collations; and composite-key
ranges beyond one equality prefix plus one range column.
