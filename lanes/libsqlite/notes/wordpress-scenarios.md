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
If a `lower(option_name)` index declares an application-defined collation, a
caller can now supply the matching PHP comparator explicitly. This maps
slug-like WordPress option names where separators such as underscores and
hyphens compare equal under a site-defined collation while the ordinary
built-in lower-expression path still rejects unsupported collations.
The custom-collation lower-expression path now also supports `IN (...)` lists
and bounded ranges. Recovery tools can request several slug-equivalent
mixed-case option names without duplicate RHS rows, ignore `NULL` RHS terms,
or scan plugin option-name bands such as `plugin-` through `plugin.` using the
site's comparator while rechecking the table row against the callback before
returning it.
First-term `upper(option_name)` expression indexes are now parsed for
ASCII-folded point, `IN (...)`, and bounded range reads. This maps databases or
recovery tools that stored an uppercase expression index instead of a lowercase
one: callers can request `siteurl,home` or a transient-prefix range, the native
reader probes the stored uppercase keys using
SQLite's built-in bytewise ASCII `upper()` semantics, rejects the expression
index as an ordinary `option_name` index, accepts only safe
`option_name IS NOT NULL` partial predicates for this path, and skips
out-of-range b-tree branches before page decoding.
First-term `trim(option_name)`, `ltrim(option_name)`, and
`rtrim(option_name)` expression indexes are now parsed for point lookups with
SQLite's default space trimming or a literal character-set argument. This maps
recovery databases where option names were accidentally padded during a manual
import or migration: callers can request `siteurl`, the native reader probes a
stored `trim(option_name)` key such as `SiteURL`, preserves `COLLATE NOCASE`
metadata, accepts only safe `option_name IS NOT NULL` partial predicates, and
returns the original row name such as ` SiteURL  ` for review or repair.
First-term `substr(option_name,start,length)` expression indexes are now
parsed for non-zero integer start and optional non-negative length literals. A
WordPress recovery tool can use a `substr(option_name,1,N)` expression index to read prefix
buckets such as `_transient_` through native index traversal, including
`COLLATE NOCASE` comparison and safe `option_name IS NOT NULL` partial
predicate checks. This remains intentionally narrower than SQLite's full
expression engine: variable-start substrings, expression `IN` lookup families
beyond `lower(column)`, `upper(column)`, and this literal-start prefix-list
path, and arbitrary functions are still future slices.
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
The length-expression path also supports bounded integer range scans with
open or inclusive upper bounds. Recovery and audit tools can inspect suspicious
option-name length bands such as medium-length migration markers without
scanning every `wp_options` row, while still using SQLite-style UTF-8 text
length behavior for the current fixture boundary.
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
First-term `json_extract(option_value,'$.key')` expression indexes are now
parsed for exact scalar lookups over strict JSON or supported JSON5 option values. Recovery and
audit tools can find plugin/theme settings such as `{"enabled":true}` through
the stored JSON expression key, with SQLite-style boolean scalars mapped to
`1`/`0`, without treating the expression index as a normal `option_value`
column index. This slice accepts only simple object-member paths and safe
`option_value IS NOT NULL` partial predicates.
The same JSON-expression path now supports bounded `IN (...)` reads for
multiple scalar buckets. Recovery and preload tools can request values such as
`enabled,disabled`, honor `COLLATE NOCASE`, ignore `NULL` RHS values for
matching, suppress duplicate RHS output, and skip unrelated JSON-key subtrees
before page decoding.
The JSON-expression path also supports bounded scalar range scans with open or
inclusive upper bounds. Recovery and audit tools can inspect numeric priority
bands or text status bands inside strict-JSON plugin settings through
`json_extract(option_value,'$.key')` without scanning every option row, while
still excluding JSON null or missing-path keys from bounded comparisons.
JSON expression row verification now falls back to a bounded SQLite JSON5
parser when strict JSON decoding fails. Recovery tools can read manually
edited plugin settings such as `{enabled: true, mode: 'dark', /* note */
rules: [{enabled:false}, {enabled:true,},],}` through stored JSON expression
indexes, while malformed JSON5 such as duplicate commas is still rejected
instead of trusting an index payload blindly.
SQLite `option_value ->> 'key'` expression indexes are now accepted for the
same simple JSON object-member lookup family. This maps plugin/theme settings
databases that use the JSON text-operator shorthand instead of
`json_extract(...)`: recovery tools can still request `$.enabled`, resolve the
arrow expression index, and verify the strict JSON or supported JSON5 scalar before returning
matching `wp_options` rows.
SQLite `option_value -> 'key'` expression indexes are now accepted as a
separate JSON-fragment lookup family. This maps plugin/theme settings
databases that index a JSON object, array, quoted string, boolean, or JSON null
fragment instead of the SQL scalar returned by `->>`: recovery tools can
request a path such as `$."settings.v1"`, compare SQLite's JSON text result,
and distinguish a stored JSON null from a missing path.
The same JSON-fragment path now supports bounded `IN (...)` and range reads.
Recovery and audit tools can request several stored JSON fragments such as a
settings object, a string state, and JSON null in one indexed pass, suppress
duplicate RHS values, and scan JSON-text channel ranges while still excluding
missing paths.
JSON expression paths now also support non-negative array indexes such as
`$.rules[0].enabled` and `$[0]`, plus reverse array indexes such as
`$.rules[#-1].enabled` and `$[#-1]`. This maps plugin/theme settings that store
ordered rule lists, feature channels, or migration stages in JSON arrays:
native recovery tools can resolve `json_extract(option_value,
'$.rules[0].enabled')`, `json_extract(option_value,'$.rules[#-1].enabled')`,
`option_value ->> '[0]'`, `option_value ->> 0`, or `option_value ->> -1`
expression indexes, distinguish arrays from object labels, treat `[#]` as
not-found for extraction, and reject malformed reverse path forms until broader
JSON mutation behavior is ported.
SQLite JSON path object-label escaping now matches the focused `json502.test`
boundary. Recovery tools can use expression indexes whose path labels contain
embedded quotes, JSON5-style hex escapes, or backslashes, including
`json_extract(option_value,'$.A"Key')`,
`json_extract(option_value,'$."plugin\x5cenabled"')`, and `option_value ->>
'a\x62c'`. This maps plugin/theme settings exports whose option JSON keys were
generated from external identifiers rather than plain PHP array keys.
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
Multi-column equality prefixes are now available through
`wordpressOptionsByIndexedNameRangeWithPrefix()`. A recovery tool can target
indexes shaped like `wp_options(autoload, option_value, option_name)`, for
example `autoload='no' AND option_value='cached-feed'` plus a transient
`option_name` range, and still avoid unrelated or damaged branches.

B-tree page freeblock chains can now be inspected directly from a page header.
WordPress recovery or import diagnostics can report reclaimed/deleted-space
regions on the schema root or `wp_options` root page, compute SQLite-style
free-space totals, and flag overlapping, out-of-usable-space, or impossible
free-space accounting before relying on an index or table page. This is a
read-only page-integrity slice, not SQLite defragmentation or page rewriting.

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

First-column range, lower-expression IN-list/range, length-expression IN-list/range,
CAST-expression IN-list/range, first-column IN-list, JSON expression point/IN-list/range,
and composite equality-prefix range scans now use bounded index b-tree traversal instead of
decoding every index page. This matters for WordPress recovery and import tools
that inspect a narrow option-name range or a small known option-name set from a
large or partially damaged database image: an unrelated out-of-range index
branch no longer has to be readable before constrained `wp_options(option_name)`,
`wp_options(lower(option_name))`, `wp_options(CAST(option_value AS INTEGER))`,
`wp_options(json_extract(option_value,'$.key'))`, or
`wp_options(autoload, option_name)` lookups can return matching rows.

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

`examples/wordpress-page-freeblocks.php` reads a WordPress-oriented SQLite
database image, inspects one b-tree page's freeblock chain, reports
SQLite-style free-space accounting, and surfaces page-local freeblock
corruption without invoking the SQLite extension.

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

`examples/wordpress-prefixed-option-name-range.php` reads a WordPress-oriented
SQLite database file, accepts a JSON equality-prefix object such as
`{"autoload":"no","option_value":"cached-feed"}`, resolves a composite index
whose next column is `option_name`, and returns options in the requested name
range. This maps recovery of a narrow subset of transient/cache rows from
large or partially damaged option databases.

`examples/wordpress-lowercase-option-lookup.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(lower(option_name))` expression index, and returns a single option
by case-folded name. This maps recovery workflows that need case-insensitive
option inspection from a database image but must not treat expression indexes
as ordinary column indexes.

`examples/wordpress-lowercase-custom-collation-option-lookup.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(lower(option_name) COLLATE WPSLUG)` expression index, and returns
matching options only when the caller supplies the matching PHP collation
callback. This maps plugin/theme settings whose option-name slugs differ by
case, underscores, or hyphens while keeping unsupported custom collations out
of the ordinary lower-expression lookup path.

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

`examples/wordpress-uppercase-options-by-name-list.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(upper(option_name))` expression index, and returns a bounded set of
ASCII-folded names such as `siteurl,home` without scanning the whole table.
This maps recovery workflows where an uppercase expression index exists and the
PHP SQLite extension is unavailable.

`examples/wordpress-uppercase-option-name-range.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(upper(option_name))` expression index, and returns options whose
ASCII-folded names fall inside caller supplied bounds. This maps transient or
migration-prefix recovery when the available expression index stores uppercase
keys rather than lowercase keys.

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

`examples/wordpress-option-name-length-range.php` reads a WordPress-oriented
SQLite database file, resolves a first-term `wp_options(length(option_name))`
expression index, and returns options whose name lengths fall inside caller
supplied bounds. This maps option-name length anomaly audits and recovery
checks without requiring the PHP SQLite extension or a full table scan.

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

`examples/wordpress-json-option-value.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON or supported JSON5 scalar value matches a requested path/value pair.
This maps plugin/theme settings recovery such as indexed enabled flags without
requiring the PHP SQLite extension or a full table scan.

`examples/wordpress-json5-option-value.php` documents the same indexed scalar
lookup for option rows whose JSON text uses SQLite JSON5 input features such
as unquoted keys, single-quoted strings, comments, extra whitespace, or
trailing commas. This maps recovery of manually edited plugin/theme settings
without requiring the SQLite extension.

`examples/wordpress-json-escaped-label-option-value.php` documents indexed
scalar lookups for JSON object labels that require SQLite path escaping, such
as embedded quotes, `\xNN` label escapes, or backslash-containing plugin
settings keys.

`examples/wordpress-json-array-option-value.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.rules[0].enabled'))`-style expression
index, and returns options whose strict JSON scalar at a non-negative array
path matches the requested value. This maps ordered plugin rule/channel
settings without scanning every serialized option row.

`examples/wordpress-json-last-array-option-value.php` reads a
WordPress-oriented SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$[#-1]'))` or `option_value ->> -1`
expression index, and returns options whose strict JSON scalar at the last
array position matches the requested value. This maps plugin channel lists,
latest migration stages, and last-rule checks without scanning every serialized
option row.

`examples/wordpress-json-option-value-list.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON scalar value is in a caller supplied list. This maps
multi-state plugin/theme settings recovery such as enabled/disabled mode lists
without scanning every `wp_options` row.

`examples/wordpress-json-option-value-range.php` reads a WordPress-oriented
SQLite database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose strict JSON scalar value falls inside caller supplied bounds. This
maps plugin/theme settings audits such as numeric priority or migration stage
bands without scanning every `wp_options` row.

`examples/wordpress-json-option-arrow.php` reads a WordPress-oriented SQLite
database file, resolves a first-term `wp_options(option_value ->> 'key')`
expression index, and returns options whose strict JSON scalar value matches
the requested label/path and scalar. This maps plugin/theme settings recovery
when the database uses SQLite's JSON text-operator shorthand.

`examples/wordpress-json-option-fragment.php` reads a WordPress-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON fragment matches a requested
path and JSON value. This maps plugin/theme settings recovery when a database
indexes a nested settings object, JSON string, boolean, or JSON null as JSON
text rather than as a SQL scalar.

`examples/wordpress-json-option-fragment-list.php` reads a WordPress-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON fragment is in a caller
supplied JSON array. This maps multi-state plugin/theme settings recovery where
object fragments, strings, booleans, and JSON null must be matched without a
full table scan.

`examples/wordpress-json-option-fragment-range.php` reads a WordPress-oriented
SQLite database file, resolves a first-term `wp_options(option_value -> 'key')`
expression index, and returns options whose JSON-text fragment falls inside
caller supplied bounds. This maps channel/stage audits where the database
stores JSON fragments through SQLite's value-operator shorthand.

`examples/wordpress-jsonb-option-value.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(json_extract(option_value,'$.key'))` expression index, and returns
options whose scalar value matches even when the underlying `option_value`
record is a SQLite JSONB BLOB. This maps plugin/theme settings databases that
were populated by SQLite JSONB functions while still running on hosts without
the PHP SQLite extension.

`examples/wordpress-jsonb-option-fixture.php` encodes strict JSON or supported
SQLite JSON5 settings text into native SQLite JSONB bytes for WordPress
`wp_options.option_value` BLOB fixtures. This maps recovery tests and import
preflight tools that need JSONB-shaped plugin settings without shelling out to
SQLite.

`examples/wordpress-trimmed-option-name.php` reads a WordPress-oriented SQLite
database file, resolves a first-term
`wp_options(trim(option_name))`/`ltrim`/`rtrim` expression index, and returns
the option whose normalized name matches the requested input. This maps
whitespace-damaged option-name recovery without requiring the PHP SQLite
extension or a full table scan.

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

`examples/wordpress-custom-collation-option-lookup.php` reads a
WordPress-oriented SQLite database file whose `wp_options(option_name)` index
was created with an application-defined collation such as `WPCASE` or
`BACKWARDS`. The caller supplies the matching PHP comparator, allowing recovery
tooling to use that index intentionally while ordinary built-in lookup paths
continue to reject unsupported collations instead of returning misleading
results.

`examples/wordpress-custom-collation-option-name-range.php` reads a bounded
`wp_options(option_name COLLATE X)` name range with a supplied comparator. This
maps plugin recovery indexes whose option-name ordering treats case,
underscores, or other slug separators differently from SQLite's built-in
`BINARY`/`NOCASE`/`RTRIM` collations, while still requiring an explicit
collation match and a comparator that returns an integer.

`examples/wordpress-custom-collation-autoload-option-name-range.php` reads a
bounded `wp_options(autoload, option_name COLLATE X)` range with a supplied
comparator for the second indexed column. This maps non-autoloaded transient or
cache recovery on sites that created custom slug/case collations while still
requiring the autoload equality prefix and a collation-safe partial predicate.

`examples/wordpress-custom-collation-prefix-option-name-range.php` reads a
bounded `option_name` range through a composite index whose equality-prefix
column uses an application-defined collation, for example
`wp_options(option_value COLLATE WPSLUG, option_name)`. The caller supplies a
collation callback map, so recovery tooling can group plugin/cache rows where
`Plugin-Core` and `plugin_core` compare equal under site-specific slug rules
while the ordinary composite path continues to reject unsupported collations.

## Next Task

Port SQLite index b-tree comparison features that are still outside the current
slice: expression indexes beyond `lower(column)`, `upper(column)`,
`trim/ltrim/rtrim(column[, literal characters])` point lookups, literal-start
`substr(column,...)`, `length(column)`, `CAST(column AS INTEGER)`, and the
named `json_extract(column,path)`, `column ->> path`, and `column -> path`
JSON scalar/fragment buckets; broader JSON path/value semantics such as JSON
mutation at `[#]`, broader JSONB output/edit behavior beyond the current
value encoder, and full JSON5 numeric/string edge parity; custom collation
coverage across automatic indexes, additional composite planner shapes, and
expression-index families beyond the explicit first-column, custom-collated
equality-prefix, `lower(option_name)` point/list/range, and autoload
equality-prefix `option_name` range recovery paths; and composite planner
shapes outside equality-prefix plus one range column.
