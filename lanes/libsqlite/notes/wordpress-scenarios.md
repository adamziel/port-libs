# libsqlite WordPress Scenario

SQLite fallback/read-write tooling for WordPress hosts where the SQLite extension is unavailable.

## JSON Aggregate ORDER BY Option Summary Scenario

Native JSON aggregate summaries now include a bounded
`json_group_array(X ORDER BY option_name)` ordering helper for copied option
values. The example `examples/wordpress-json-aggregate-option-summary.php`
now streams copied `wp_options.option_value` rows with `option_name` order
keys, reports ordered text aggregate output, and decodes the ordered JSONB
result for review. This gives WordPress import tooling a local-only way to
produce deterministic option summaries before migration without requiring the
SQLite extension.

Status delta 2026-05-26 isolated refill: added
`SQLiteJsonAggregate::jsonGroupArrayOrderBy()`,
`SQLiteJsonAggregateState::stepArrayOrderBy()`, and
`SQLiteJsonAggregateState::finalizeOrderedArray()` with focused tests for
NULL-low ascending ordering, stable equal-key ties, text and numeric order
keys, SQL NULL values, JSON subtype fragments, JSONB BLOB values, empty
aggregate finalization, invalid function names, malformed raw BLOB rejection,
and JSONB output decoding. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON aggregate, constructor
value coercion, JSON subtype, JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Distinct Option Summary Scenario

Native JSON aggregate summaries now include a bounded
`json_group_array(DISTINCT X)` row de-duplication helper for copied option
values. The example `examples/wordpress-json-aggregate-option-summary.php`
now includes duplicated string and JSONB option values, reports direct and
step/final distinct JSON arrays, and decodes the JSONB distinct result for
review. This gives WordPress import tooling a local-only way to spot unique
settings payloads before migration without requiring the SQLite extension.

Status delta 2026-05-26 isolated refill: added
`SQLiteJsonAggregate::jsonGroupArrayDistinct()`,
`SQLiteJsonAggregateState::stepArrayDistinct()`, and
`SQLiteJsonAggregateState::finalizeDistinctArray()` with focused tests for
first-seen ordering, SQL NULL collapse, boolean/integer equality, JSON subtype
fragments, JSONB BLOB values, empty aggregate finalization, invalid function
names, and malformed raw BLOB rejection. Dependency closure: no new support
component is needed; the slice reuses existing lane-local JSON aggregate,
constructor value coercion, JSON subtype, JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Step/Final Option Summary Scenario

Native JSON aggregate summaries now include a bounded step/final state helper
for ordered `json_group_array()` and `json_group_object()` rows. The example
`examples/wordpress-json-aggregate-option-summary.php` now streams copied
`wp_options.option_value` rows into `SQLiteJsonAggregateState`, finalizes text
and JSONB aggregate results through uppercase SQL function names, and reports
the step counts beside the accepted direct aggregate output. This gives
WordPress import and repair tooling a local-only path that mirrors SQLite's
aggregate lifecycle without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`SQLiteJsonAggregateState`, focused step/final tests for text and JSONB array
and object results, invalid-name propagation, empty aggregate finalization,
and updated the existing WordPress smoke to stream copied options through the
new state object. Dependency closure: no new support component is needed; the
slice reuses existing lane-local JSON aggregate dispatch, JSON subtype, JSONB,
BLOB, constructor value coercion, and SQL NULL support.

## `json_remove()`/`jsonb_remove()` Argument-Vector Cleanup Scenario

Native JSON removal now includes bounded SQL-style argument-vector dispatch for
`json_remove()` and `jsonb_remove()` with case-insensitive function lookup.
The example `examples/wordpress-json-remove-sql-dispatch-preflight.php`
exercises copied `wp_options.option_value` inputs through uppercase
argument-vector dispatch for text and JSONB result typing, multiple path
removals, SQL NULL input, and root removal. This gives WordPress import and
repair tooling a local-only cleanup preflight that mirrors SQLite's SQL entry
point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`removeSqlFunctionArguments()`, switched direct remove function-name
validation to case-insensitive lookup, added focused arity, JSON argument type,
path type, and invalid-name rejection tests, and updated the existing
WordPress smoke to report uppercase argument-vector dispatch. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON path, JSON5, JSONB, canonical JSON, BLOB, and SQL NULL support.

## `json_insert()`/`json_set()`/`json_replace()` Option Mutation Dispatch Scenario

Native JSON option mutation now includes bounded SQL-style argument-vector
dispatch for `json_insert()`, `jsonb_insert()`, `json_set()`, `jsonb_set()`,
`json_replace()`, and `jsonb_replace()` with case-insensitive function lookup.
The example `examples/wordpress-jsonb-mutate-option-field.php` now exercises
copied `wp_options.option_value` JSON through uppercase argument-vector
dispatch while preserving text versus JSONB result typing, JSON subtype
fragments, JSONB replacement values, and SQL NULL propagation. This gives
WordPress import and repair tooling a local-only mutation preflight that
mirrors SQLite's SQL entry points without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`mutateSqlFunctionArguments()`, switched direct mutation function-name
validation to case-insensitive lookup, added focused arity/input/path invalid
argument tests, and updated the existing WordPress smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON path mutation, JSON subtype,
JSONB, BLOB, and SQL NULL support.

## JSON Aggregate Option Summary Dispatch Scenario

Native JSON aggregate summaries now include bounded SQL-style argument-vector
dispatch for `json_group_array()`, `jsonb_group_array()`,
`json_group_object()`, and `jsonb_group_object()` with case-insensitive
function lookup. The example
`examples/wordpress-json-aggregate-option-summary.php` exercises copied
`wp_options.option_value` rows through uppercase argument-vector dispatch for
text and JSONB aggregate result typing, JSON subtype fragments, JSONB blobs,
booleans, and SQL NULL option values. This gives WordPress import and repair
tooling a local-only option summary path that mirrors SQLite's SQL entry
points without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`jsonGroupArraySqlFunctionArguments()` and
`jsonGroupObjectSqlFunctionArguments()`, switched direct aggregate
function-name validation to case-insensitive lookup, added focused invalid
name and malformed object row rejection tests, and updated the existing
WordPress smoke to report uppercase argument-vector dispatch. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON constructor value coercion, JSON subtype, JSONB, BLOB, and
SQL NULL support.

## `json_patch()`/`jsonb_patch()` Option-Value Merge Dispatch Scenario

Native JSON merge-patch now includes bounded SQL-style argument-vector
dispatch for `json_patch()` and `jsonb_patch()` with case-insensitive function
lookup. The example
`examples/wordpress-json-patch-sql-dispatch-preflight.php` exercises copied
`wp_options.option_value` inputs through uppercase argument-vector SQL
dispatch for JSON text, SQLite JSON5 patch text, copied JSONB blobs, cast text
BLOB handling, JSONB result typing, and SQL NULL propagation. This gives
WordPress import and repair tooling a local-only merge-patch preflight that
mirrors SQLite's SQL entry point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`patchSqlFunctionArguments()`, switched direct patch function-name validation
to case-insensitive lookup, added focused arity and invalid-name rejection
tests, and updated the existing WordPress smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON canonicalization, JSON5,
JSONB patch, BLOB, and SQL NULL support.

## `json_quote()` Option-Value SQL Dispatch Scenario

Native JSON SQL-value quoting now includes bounded SQL-style argument-vector
dispatch for `json_quote()` with case-insensitive function lookup. The example
`examples/wordpress-json-quote-option-preflight.php` exercises copied
`wp_options.option_value` inputs through uppercase argument-vector SQL
dispatch for SQL NULL, integers, REAL values, copied text settings,
control-character text, JSONB blobs, and raw BLOB rejection. This gives
WordPress import and repair tooling a local-only preflight that mirrors
SQLite's SQL entry point without requiring the SQLite extension.

Status delta 2026-05-25 isolated refill: added
`jsonQuoteSqlFunctionArguments()`, switched direct quote function-name
validation to case-insensitive lookup, added focused arity and invalid-name
rejection tests, and updated the existing WordPress smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSONB, JSON subtype, BLOB, SQL
scalar, and SQL NULL support.

## `json_type()`/`json_array_length()` Option-Value Inspection Dispatch Scenario

Native JSON inspection now includes bounded SQL-style argument-vector dispatch
for `json_type()` and `json_array_length()` with case-insensitive function
lookup. The example `examples/wordpress-json-inspection-preflight.php`
exercises copied `wp_options.option_value` inputs through direct inspection,
direct SQL dispatch, and uppercase argument-vector SQL dispatch for strict
JSON text, SQLite JSON5 text, cast text BLOBs, JSONB blobs, and SQL NULL
option values. This gives WordPress import and repair tooling a local-only
preflight that mirrors SQLite's SQL entry points without requiring the SQLite
extension.

Status delta 2026-05-25 isolated refill: added
`inspectionSqlFunctionArguments()`, switched direct inspection function-name
validation to case-insensitive lookup, added focused arity/path-type
rejection tests, and updated the existing WordPress smoke to report uppercase
argument-vector dispatch. Dependency closure: no new support component is
needed; the slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB,
and SQL NULL support.

## `json_pretty()` SQL-Dispatch Option-Value Review Scenario

Native JSON pretty-printing now includes a bounded SQL function-name dispatch
helper for `json_pretty()` with SQLite-style case-insensitive function lookup.
The example
`examples/wordpress-json-pretty-option-review.php` exercises the dispatch
path for copied `wp_options.option_value` inputs, including strict JSON text,
SQLite JSON5 text, cast text BLOBs, JSONB blobs, SQL NULL option values,
JSON subtype fragments, scalar SQL option values including booleans, fractional floats, and whole REAL
values, malformed settings, and custom text/numeric/boolean indentation. This gives WordPress migration and
repair tooling a local-only review path that mirrors SQLite's SQL entry point
without requiring the SQLite extension.

Status delta 2026-05-25 isolated rework: added `jsonPrettySqlFunction()`,
kept invalid-name rejection, accepted uppercase SQL spelling through direct
and argument-vector dispatch, added one-or-two argument-vector dispatch
coverage, added subtype input and malformed-input dispatch smoke coverage,
added scalar SQL argument-vector coercion coverage for integer, float, true, and false inputs,
aligned direct SQL-dispatch scalar coercion with the argument-vector path,
and updated the existing WordPress smoke to call the SQL-dispatch helper
through its argument-vector entry point. This preserves
accepted json_extract/jsonb_extract subtype dispatch and json_each
table-valued row evidence while making the deferred json_pretty patch
additive. Dependency closure: no new support component is needed; the slice
reuses existing lane-local JSON canonicalization, JSON5, JSONB, BLOB, subtype, and
pretty formatter support and counts no shared support-library progress.
Priority-keeper refresh 2026-05-25T09:23Z keeps the same behavior cluster and
adds focused malformed JSON propagation coverage through the argument-vector
SQL-dispatch path, preserving the already accepted manifest/status evidence.
Priority-keeper refresh 2026-05-25T09:58Z adds the missing direct-dispatch
`true` scalar assertion without changing the WordPress smoke surface.
Priority-finisher refresh 2026-05-25T10:13Z preserves whole REAL scalar output
such as `3.0` through direct and argument-vector SQL dispatch and adds that
case to the WordPress smoke surface.
Clean-integrator rebase 2026-05-25T10:17Z also keeps signed integer and
fractional float option-value smoke coverage in the same SQL-dispatch cluster.
Priority-finisher refresh 2026-05-25T10:28Z adds direct SQL-dispatch coverage
for cast text BLOB and JSON subtype custom indentation, and the WordPress
smoke now reports direct `JSON_PRETTY` output beside argument-vector output.
Priority-keeper refresh 2026-05-25T10:40Z adds boolean true and fractional
REAL custom-indent option rows so local review output covers SQLite SQL scalar
coercion for the second `json_pretty(JSON, INDENT)` argument too.
Priority-keeper rework 2026-05-25T10:50Z adds the missing boolean false
custom-indent option row and direct-dispatch assertion so both SQL-dispatch
entry points cover SQLite's false-to-`0` second-argument coercion.
Priority-rework refill 2026-05-25T11:02Z adds explicit cast text-BLOB JSON
input review through both direct and argument-vector SQL dispatch, including a
custom text indentation row in the WordPress smoke. This keeps the slice
inside the accepted json_pretty SQL-dispatch cluster and preserves existing
json_extract/jsonb_extract and json_each evidence.
Priority-keeper rework 2026-05-25T11:10Z additively covers JSONB option blobs
with custom indentation through both direct and argument-vector SQL dispatch,
so local review output now exercises the same indentation path for SQLite JSONB
storage as for text JSON and cast text BLOB inputs.
Priority-keeper rework 2026-05-25T11:27Z adds the matching focused assertions
for JSONB option blobs through both SQL-dispatch paths with default
indentation, aligning the native tests with the existing `jsonb_settings`
WordPress smoke row.
Priority-refill rework 2026-05-25T12:13Z adds the matching SQL NULL
first-argument plus custom-indent second-argument row for copied option values,
so both direct and argument-vector SQL-dispatch paths return NULL for
`json_pretty(NULL, '--')` instead of treating the indent as meaningful output.
Supervisor-rework refill 2026-05-25T12:53Z adds a JSON subtype option-value
smoke row and matching focused assertions for default indentation through both
direct and argument-vector SQL-dispatch paths.
Dependency closure remains unchanged: no new support component is needed.

## `json_each()` Option-Value Expansion Scenario

Native JSON table-valued inspection now includes a bounded `json_each(X[,P])`
row producer for strict JSON text, SQLite JSON5 text, JSONB blobs, missing
paths, scalar paths, and SQL NULL option values. The example
`examples/wordpress-json-each-option-settings.php` expands copied
`wp_options.option_value` plugin settings at the root, `$.plugin`, and
`$.plugin.rules`, reporting SQLite-shaped `key`, `value`, `type`, `atom`,
`id`, `parent`, `fullkey`, and `path` columns without requiring the SQLite
extension. This gives WordPress import and repair tooling a local-only way to
review setting members and rule arrays before import.

Status delta 2026-05-25 isolated micro-slice: added `SQLiteJsonEach`, focused
tests, and a WordPress smoke. The slice covers immediate child rows only; full
recursive `json_tree()`, hidden `json`/`root` columns, planner behavior, and
virtual table cursor internals remain out of scope. Focused verification is
recorded in `lane-status.json`. Blocker: no hydrated upstream cache exists in
this isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior focused JSON1/JSONB runner evidence and maps the
table-valued-function row-shape boundary natively. Dependency closure: no new
support component is needed; the slice reuses existing lane-local JSON path,
JSON5, JSONB, canonical encoding, and BLOB support and counts no shared
support-library progress.

## `json_tree()` Recursive Option-Value Expansion Scenario

Native JSON table-valued inspection now includes a bounded `json_tree(X[,P])`
row producer for strict JSON text, SQLite JSON5 text, JSONB blobs, missing
paths, scalar paths, and SQL NULL option values. The example
`examples/wordpress-json-tree-option-settings.php` recursively expands copied
`wp_options.option_value` plugin settings at the root, `$.plugin`, and
`$.plugin.rules`, reporting SQLite-shaped `key`, `value`, `type`, `atom`,
`id`, `parent`, `fullkey`, and `path` columns without requiring the SQLite
extension. This gives WordPress import and repair tooling a local-only way to
review nested setting trees and rule arrays before import.

Status delta 2026-05-25 isolated micro-slice: added `SQLiteJsonTree`, focused
tests, and a WordPress smoke. The slice covers recursive row production and
parent ids; hidden `json`/`root` columns, planner behavior, and virtual table
cursor internals remain out of scope. Focused verification is recorded in
`lane-status.json`. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior focused JSON1/JSONB runner evidence and maps the recursive
table-valued-function row-shape boundary natively. Dependency closure: no new
support component is needed; the slice reuses existing lane-local JSON path,
JSON5, JSONB, canonical encoding, and BLOB support and counts no shared
support-library progress.

## JSON Operator Parenthesized RHS Index Preflight Scenario

Native JSON operator expression-index preflight now folds parenthesized scalar
RHS constants for copied `wp_options` JSON operator indexes. The example
`examples/wordpress-json-operator-parenthesized-rhs.php` checks indexes such
as `option_value ->> ('cache')`, `option_value ->> (1)`, and
`option_value -> ('settings.v1')`, then proves the normalized paths can resolve
index root pages and option rows without the SQLite extension. Arithmetic and
broader SQL expressions remain unsupported so this does not over-credit full
SQLite expression evaluation.

## `json_extract()`/`jsonb_extract()` Option-Value Preflight Scenario

Native JSON extraction now follows a bounded SQLite `json_extract(X,P...)`
SQL-result typing slice for strict JSON text, SQLite JSON5 text, cast text
BLOBs, JSONB blobs, SQL NULL option values, missing paths, scalar paths,
object/array paths, multi-path JSON array output, and the result-type boundary
where `jsonb_extract()` returns JSONB blobs for object/array or multi-path
results while preserving SQL scalar result typing for scalar paths. The example
`examples/wordpress-json-extract-option-preflight.php` checks local
`wp_options.option_value`-shaped copied plugin settings and reports extracted
enabled flags as SQLite-style `1`/`0`, text titles as SQL text, object paths as
canonical JSON text, missing paths as NULL, multi-path summaries as JSON
arrays, and decoded JSONB summaries with their hex bytes. This gives WordPress
import and repair tooling a local-only way to read copied plugin settings
without requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added `json_extract()` and
`jsonb_extract()` SQL function-name dispatch, focused tests, and updated the
WordPress smoke to call the dispatch helper and report JSONB result blobs.
Focused verification is recorded in `lane-status.json` after local checks.
Blocker: no hydrated upstream cache exists in this isolated worktree, so no
fresh SQLite testfixture run was performed; this slice reuses prior
`json101.test`, `json102.test`, and `jsonb.test` extract evidence. Next task:
integrator acceptance, then one additional bounded libsqlite behavior slice
with its own evidence. Dependency closure: no new support component is needed;
the slice reuses existing lane-local JSON extraction, JSON path, inspection,
JSONB, and BLOB support and counts no shared support-library progress.

## `json_extract()` Subtype Diagnostics Scenario

Native JSON extraction now also exposes a bounded JSON-argument path for
SQLite subtype propagation when object/array or multi-path
`json_extract(X,P...)` results are passed into JSON constructors. The example
`examples/wordpress-json-extract-subtype-option-diagnostics.php` checks local
strict JSON, JSON5 text, and JSONB `wp_options.option_value`-shaped copied
plugin settings, wraps extracted rules and summaries with `json_array()` and
`json_object()`, and verifies that nested JSON values are embedded as JSON
rather than double-quoted text. This gives WordPress migration and repair
tooling local-only constructor diagnostics before copied plugin settings are
imported, without requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added
`extractJsonArgumentSqlFunction()` for `json_extract()`/`jsonb_extract()`
function-name dispatch at the JSON-constructor argument boundary, focused
tests, and a WordPress smoke. `json_extract()` object/array and multi-path
arguments preserve SQLite JSON subtype text; `jsonb_extract()` object/array
and multi-path arguments preserve SQLite JSONB blobs; scalar, missing, and
SQL NULL arguments keep SQL typing. Focused verification is recorded in
`lane-status.json`. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json102.test`, `subtype1.test`, and
`jsonb01.test` evidence. Next task: integrator acceptance, then one
additional bounded libsqlite behavior slice with its own evidence. Dependency
closure: no new support component is needed; the slice reuses existing
lane-local JSON extraction, JSON path, inspection, JSONB, BLOB, subtype, and
constructor support and counts no shared support-library progress.

## `json_remove()` Option-Value Cleanup Scenario

Native JSON removal now follows a bounded SQLite `json_remove(X,P...)`
text-result slice for strict JSON text, SQLite JSON5 text, cast text BLOBs,
SQLite JSONB blobs, SQL NULL option values, no-path canonicalization, multiple
paths in SQLite argument order, missing-path no-ops, array reverse indexes, and
root `$` removal to SQL NULL. The example
`examples/wordpress-json-remove-option-preflight.php` checks local
`wp_options.option_value`-shaped copied plugin settings and removes obsolete
settings such as `$.plugin.legacyToken` and stale rule entries before import.
This gives WordPress import and repair tooling a local-only cleanup path
without requiring the SQLite extension.

## `json_remove()`/`jsonb_remove()` Result-Type Dispatch Scenario

Native JSON removal now includes a bounded SQL-dispatch helper for the SQLite
result-type boundary: `json_remove()` returns canonical JSON text, while
`jsonb_remove()` returns SQLite JSONB blob bytes. The example
`examples/wordpress-json-remove-sql-dispatch-preflight.php` checks copied
`wp_options.option_value` plugin settings and can report either decoded JSONB
plus hex bytes or text JSON after obsolete paths are removed. This gives
WordPress import and repair tooling a local-only way to preserve JSONB fixture
typing during cleanup without requiring the SQLite extension.

## `json_patch()`/`jsonb_patch()` Result-Type Dispatch Scenario

Native JSON merge patching now includes a bounded SQL-dispatch helper for the
SQLite result-type boundary: `json_patch()` returns canonical JSON text, while
`jsonb_patch()` returns SQLite JSONB blob bytes. The example
`examples/wordpress-json-patch-sql-dispatch-preflight.php` checks copied
`wp_options.option_value` plugin settings and applies RFC-7396 merge patches
where object-member `null` values delete keys, nested objects merge, and arrays
replace whole arrays. This gives WordPress import and repair tooling a
local-only way to preserve JSONB fixture typing while applying plugin setting
patches before import, without requiring the SQLite extension.

## JSON Operator `min()`/`max()` RHS Index Preflight Scenario

Native JSON operator expression-index preflight now folds reduced SQLite
`min()`/`max()` RHS constants over homogeneous literal strings or homogeneous
numeric literals. The example `examples/wordpress-json-operator-minmax-rhs.php`
checks copied `wp_options` JSON operator indexes such as
`option_value ->> min('seo','cache')`,
`option_value ->> max('plugin.enabled','plugin.disabled')`, and
`option_value ->> min(2,1)`, then proves the normalized paths can resolve
index root pages and option rows without the SQLite extension. Mixed-type and
single-argument calls remain unsupported so broader SQLite scalar semantics do
not get over-credited.

## `json_pretty()` Option-Value Review Scenario

Native JSON pretty-printing now follows SQLite's `json_pretty(JSON[,INDENT])`
boundary for strict JSON text, SQLite JSON5 text, cast text BLOBs, JSONB
blobs, SQL NULL option values, malformed JSON, and custom indentation. The
example `examples/wordpress-json-pretty-option-review.php` checks local
`wp_options.option_value`-shaped inputs for copied strict plugin settings,
JSON5 plugin settings with comments and trailing commas, tab-indented review
output, cast text BLOBs, JSONB option blobs, NULL values, and malformed
duplicate-comma settings. For WordPress migration and repair tooling this
gives a local-only way to generate SQLite-style review output for copied
plugin settings without requiring the SQLite extension or shelling out to
SQLite.

## `json(X)` Option-Value Canonicalization Scenario

Native JSON canonicalization now follows SQLite's `json(X)` boundary for
strict JSON text, SQLite JSON5 text, cast text BLOBs, JSONB blobs, malformed
JSON, and SQL NULL option values. The example
`examples/wordpress-json-canonical-option-preflight.php` checks local
`wp_options.option_value`-shaped inputs for copied strict plugin settings,
JSON5 plugin settings with comments and trailing commas, cast text BLOBs,
JSONB option blobs, NULL values, and malformed duplicate-comma settings. For
WordPress migration and repair tooling this gives a local-only way to produce
SQLite-style canonical JSON before plugin settings are imported or compared,
without requiring the SQLite extension or shelling out to SQLite.

## JSON Constructor Option Diagnostics Scenario

Native JSON constructor diagnostics now follow SQLite's `json_array()` and
`json_object()` SQL-value boundary for SQL NULL, numeric values, text values,
`TRUE`/`FALSE` integer expressions, JSON subtype passthrough, JSONB BLOB
passthrough, raw BLOB rejection, and `json_object()` label/arity errors. The
example `examples/wordpress-json-constructor-option-diagnostics.php` builds
local `wp_options` import reports and migration queue diagnostics before
copied plugin settings are trusted. For WordPress migration and repair tooling
this gives a local-only way to construct SQLite-style JSON diagnostics without
requiring the SQLite extension or shelling out to SQLite.

## `json_quote()` Option-Value Preflight Scenario

Native JSON quoting now follows SQLite's `json_quote(X)` SQL-value boundary
for SQL NULL, numeric values, copied TEXT settings, control-character TEXT,
JSONB option blobs, raw BLOB rejection, and superficial-only malformed JSONB
errors. The example `examples/wordpress-json-quote-option-preflight.php`
checks `wp_options.option_value`-shaped values before import and reports the
quoted JSON text or SQLite-style rejection status. For WordPress migration and
repair tooling this gives a local-only way to render copied scalar option
values into JSON diagnostics, preserve JSONB option blobs as JSON text, and
reject raw BLOBs before plugin settings are trusted without requiring the
SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added `json_quote()` SQL
function-name dispatch, focused tests, and updated the WordPress smoke to call
the dispatch helper. Focused verification is recorded in `lane-status.json`
after local checks. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json102.test`, and `subtype1.test`
`json_quote()` evidence. Next task: integrator acceptance, then one additional
bounded libsqlite behavior slice with its own evidence. Dependency closure: no
new support component is needed; the slice reuses existing lane-local JSON
quote, JSON subtype, JSONB, and BLOB support and counts no shared
support-library progress.

## JSON Type And Array-Length Option-Value Inspection Scenario

Native JSON inspection now follows SQLite's `json_type(X[,P])` and
`json_array_length(X[,P])` boundary for strict JSON text, JSON5 text, cast
text BLOBs, JSONB blobs, missing paths, scalar paths, and SQL NULL option
values. The example `examples/wordpress-json-inspection-preflight.php` checks
`wp_options.option_value`-shaped inputs for plugin settings roots, nested
plugin objects, plugin `modes` arrays, missing plugin paths, and NULL values.
For WordPress migration and repair tooling this gives local-only shape checks
that can distinguish object, array, scalar, missing, JSONB, and JSON5 inputs
before copied plugin settings are imported or trusted.

## `json_error_position()` Option-Value Diagnostics Scenario

Native JSON diagnostics now follow SQLite's `json_error_position(X)` boundary
for text, JSON5, BLOB, JSONB, and SQL NULL option values. The example
`examples/wordpress-json-error-position-preflight.php` checks
`wp_options.option_value`-shaped inputs for JSON5 plugin settings, duplicate
commas, nested malformed copied settings, leading-zero numeric mistakes, cast
text BLOBs, valid JSONB blobs, superficial-only corrupt JSONB blobs, and NULL
values. For WordPress migration and repair tooling this gives local-only
offsets that can be shown in diagnostics or used to route copied plugin
settings to strict import, JSON5 normalization, JSONB repair, or rejection
before the SQLite extension is available.

## `json_valid()` Option-Value Preflight Scenario

Native JSON validity preflight now follows SQLite's `json_valid(X, FLAGS)`
dispatcher across strict JSON text, SQLite JSON5 text, BLOB fallback, JSONB,
and SQL NULL option values. The example
`examples/wordpress-json-validity-preflight.php` checks local
`wp_options.option_value`-shaped inputs for strict plugin settings JSON, JSON5
plugin settings, malformed copied text, cast text BLOBs, valid JSONB blobs,
superficial-only corrupt JSONB blobs, and NULL values. For WordPress migration
and repair tooling this gives a local-only way to decide whether copied plugin
settings should be accepted as strict JSON, accepted only under SQLite JSON5
rules, treated as a text BLOB fallback, routed through JSONB strict validation,
or rejected before import.

## JSONB Validity Preflight Scenario

Native JSONB preflight now distinguishes SQLite's fast `json_valid(X,4)`
superficial BLOB check from strict recursive JSONB validation. The example
`examples/wordpress-jsonb-validity-preflight.php` checks four local
`wp_options.option_value`-shaped inputs: a valid plugin settings JSONB blob, a
large corrupt BLOB that passes SQLite's superficial flag-4 header check but
fails strict validation, a cast text JSON BLOB that is rejected at SQLite's
ambiguous small-BLOB boundary, and a scalar null header with a non-zero
payload. For WordPress migration and repair tooling this lets a local-only
import preflight cheaply triage copied JSONB option blobs and route
superficial-only settings to strict decode or repair before plugin settings
are trusted.

## JSON Path Validation Preflight Scenario

Native expression-index preflight now validates full SQLite JSON paths before
trusting copied `wp_options` schema metadata. The example
`examples/wordpress-json-path-validation-preflight.php` builds a local fixture
with one valid expression index:

```sql
option_value ->> '$.""'
```

and two malformed copied-schema expression indexes:

```sql
option_value ->> '$.plugin[#-]'
json_extract(option_value, '$.')
```

For WordPress migration and database-repair tooling this prevents a damaged or
hand-copied schema row from making native recovery code trust an unusable JSON
expression-index root page. The scenario reports `$.""` as valid, `$.`,
`$.plugin[#-]`, and `$.plugin[#9]` as invalid, resolves root page 3 for the
valid empty-label path, skips the malformed plugin-path root page, returns
`plugin_empty_label_settings`, and stays local-only without requiring the
SQLite extension.

## JSON Operator json_quote() RHS Scenario

Native JSON operator expression-index preflights now fold direct SQLite
`json_quote(VALUE)` constants for copied `wp_options` indexes when SQLite's
JSON rendering yields a reusable abbreviated path. The example
`examples/wordpress-json-operator-json-quote-rhs-forms.php` builds a local
fixture with these indexes:

```sql
option_value ->> json_quote(NULL)
option_value ->> json_quote(123)
option_value ->> json_quote(1.25)
```

For WordPress migration and repair tooling this prevents copied plugin
settings from ignoring SQLite's JSON rendering for SQL `NULL`, integer, and
REAL RHS values inside schema SQL. The scenario reports `$.null`, `$."123"`,
and `$."1.25"`, uses root pages 3-5, returns expected
`plugin_json_quote_*` rows, leaves direct quoted text, raw BLOB, and
invalid-arity RHS outputs unsupported as reusable paths, and stays local-only
without requiring the SQLite extension.

## Current Native Slice

Native SQLite database header parser, SQLite varint decoder and encoder,
b-tree page header parser for schema/root pages, table leaf and table interior cell
parsing, a page-backed database reader, SQLite record serial decoding, and
`sqlite_schema` table-b-tree traversal for WordPress table discovery. The
write-side preflight slice now also serializes SQLite records as UTF-8,
UTF-16LE, or UTF-16BE according to the database header text encoding, plus
table-leaf cells and clean table-leaf pages for minimal fixture or
repair-planning images that can be parsed back by the native reader. The
current slice also
decodes bounded table rows and maps the standard
`wp_options` row shape into `option_id`, `option_name`, `option_value`, and
`autoload` fields without using the PHP SQLite extension. Large
`option_value` records that spill from a table leaf cell into SQLite overflow
pages are now reassembled through the native page reader. Rowid-bounded
`wp_options` reads can now scan `option_id` bands across table-interior pages,
honor inclusive/exclusive upper bounds and limits, and prune unrelated damaged
branches before reading leaf cells, which maps resumable WordPress option
imports and partial database recovery when no `option_name` index is usable.
Overflow-page
fixtures can also be assembled with caller-supplied non-contiguous page
numbers and reserved-byte usable sizes, mapping repair/preflight workflows
where reusable freelist pages become a new large `wp_options.option_value`
chain. Actual freelist trunk metadata is now readable from database images, so
repair tooling can choose reusable pages from header/trunk state before
building that overflow chain. Allocation planning now also returns the
mutated first-page header and freelist trunk page images after reusable pages
are consumed, including leaf-array replacement, emptied-trunk removal, and
append-after-depletion page numbers for bounded generated-write preflight.
Free planning now mirrors SQLite's bounded `freePage2` behavior for repair
preflight: obsolete pages can be inserted as leaves on the first freelist trunk
or promoted into a new first trunk when the freelist is empty or the first
trunk is compatibility-full. Bounded insert planning now combines these
write-side primitives for explicit-rowid `wp_options` fixtures whose root is a
single table leaf page: the planner returns first-page, table-page,
overflow-page, freelist-trunk, and, for explicit `option_name` indexes,
single-leaf, root-growth, no-split multi-page, same-depth leaf-split,
parent-root-split, or `WHERE option_name IS NOT NULL` partial index page
images for a new option row. It also handles explicit single-leaf
`autoload, option_name` composite indexes plus matching `sqlite_autoindex_*`
automatic UNIQUE/PRIMARY KEY index shapes. It rejects duplicate rowids or
option names and still refuses unsupported composite shapes, unsafe partial
predicates, expression indexes, unsupported automatic indexes, non-root
parent-page splits, or index-overflow cases instead of leaving stale
secondary indexes behind.
Bounded replacement planning handles index-free, single-leaf `wp_options`
fixtures for both shrink and large-value rewrites. Large replacement payloads
allocate their new overflow chain before obsolete overflow pages are returned
to freelist metadata, matching SQLite's b-tree update ordering and avoiding
accidental same-operation reuse of the old chain. Replacement planning also
allows explicit single-leaf full or safe partial `option_name` indexes when
the key and rowid are unchanged, verifies that the index already points to the
replaced row, and can move a single-leaf or multi-page
`autoload, option_name` composite index entry when an `autoload` rewrite
changes the leading key. The same bounded maintenance now also splits a full
destination composite-index leaf when the parent page can absorb the promoted
divider. Inferred `sqlite_autoindex_*` UNIQUE/PRIMARY KEY indexes whose
columns match `option_name` or `autoload, option_name` remain supported for
the bounded single-leaf write shapes. The planner still rejects unsupported
index shapes, unsafe partial predicates, expression indexes, unsupported
automatic indexes, overflowing non-root parent-page splits, source-leaf
rebalancing, or index-overflow cases beyond bounded root growth instead of
leaving stale secondary indexes behind.
Replacement planning can now also locate a target `wp_options` row below a
table-interior root, rewrite only the table leaf that contains the option, and
leave the interior table page unchanged when the replacement cell fits within
the existing leaf. This maps larger WordPress SQLite images where repair tools
need to update a single option in a multi-page table before the lane supports
general table-leaf splits, rebalancing, journaling, or WAL.
When the larger replacement makes a table leaf split, the planner now handles
both root-level table-interior parents and one-level-deeper non-root
table-interior parents that have room for the new divider. The root page is
left unchanged for the non-root case while the lower parent receives the old
leaf's new max rowid and a new right-most child pointer. Overflowing non-root
parent pages still remain outside this bounded slice.
Explicit
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

Large `wp_options` replacement preflights now include the bounded case where a
target table leaf split overflows a full non-root table-interior parent while
the root can still absorb the promoted divider. This maps larger WordPress
SQLite database images with a deeper options table: repair tooling can rewrite
one expanded option row, split the target leaf, split the full lower parent,
update the root separators, and inspect the resulting page images without the
SQLite extension. The focused example is
`examples/wordpress-nonroot-table-parent-split-option-replacement-plan.php`.
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
JSON5 non-finite numbers now follow SQLite's JSON normalization boundary:
`+Infinity` and `-Infinity` can be matched through scalar JSON expression
indexes and through `->` fragment indexes as `9e999` and `-9e999`, while
`NaN` is treated as JSON null. This maps plugin/theme option values that use
JSON5 sentinels for unlimited cache TTLs, disabled quotas, or unset import
limits, and the JSONB fixture example can generate matching BLOB values for
preflight/recovery tests.
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
The native varint encoder now gives WordPress recovery/import tools a bounded
write-side primitive for preflighting generated `wp_options` table-leaf cell
payload-length and rowid prefixes before broader raw b-tree page writing is
ported.
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

`examples/wordpress-jsonb-remove-option-field.php` removes one or more JSON
paths from strict JSON, supported SQLite JSON5, or SQLite JSONB option-value
fixtures and prints the resulting JSONB bytes. This maps WordPress recovery
and migration preflight workflows that need to strip obsolete or sensitive
plugin settings from `wp_options.option_value` JSONB blobs while preserving
SQLite-style object-member, array-index, reverse-index, missing-path, and root
removal behavior.

`examples/wordpress-jsonb-mutate-option-field.php` applies SQLite-style
`insert`, `set`, or `replace` edits to strict JSON, supported SQLite JSON5, or
SQLite JSONB option-value fixtures and prints the resulting JSONB bytes. This
maps WordPress recovery and migration preflight workflows that need to add
migration markers, append rule objects, replace stale plugin settings, or
leave existing fields untouched according to SQLite's
`jsonb_insert`/`jsonb_set`/`jsonb_replace` path semantics.

`examples/wordpress-jsonb-array-insert-option-field.php` applies SQLite-style
`jsonb_array_insert` edits to strict JSON, supported SQLite JSON5, or SQLite
JSONB option/meta fixtures and prints the resulting JSONB bytes. This maps
WordPress import preflight workflows that need to insert a migration queue
entry before an existing option-array item or append postmeta migration keys
while preserving SQLite's array-index, reverse-index, missing-path, and
non-array no-op boundaries.

`examples/wordpress-jsonb-inspect-option-arrays.php` checks SQLite JSONB
option/meta fixture paths with `json_type` and `json_array_length` semantics.
This maps WordPress import and migration preflight workflows that need to
confirm option migration queues or postmeta key lists are present arrays, while
distinguishing missing paths from existing scalar or object targets before
array insertion, append, or reorder steps.

`examples/wordpress-jsonb-patch-option-field.php` applies SQLite-style
RFC-7396 merge patches to strict JSON, supported SQLite JSON5, or SQLite JSONB
option-value fixtures and prints the resulting JSONB bytes. This maps
WordPress import and recovery preflight workflows that need to apply a patch
object where `null` removes obsolete plugin settings, nested objects merge,
and arrays such as rule lists or channel lists are replaced as complete
values.

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

`examples/wordpress-table-leaf-page-assembly.php` assembles a minimal two-page
SQLite database image containing a `wp_options` table and a `siteurl` row using
only native PHP record, table-leaf cell, and table-leaf page encoders. The
script immediately parses the generated bytes through the native reader,
making it useful for fixture generation and repair preflight workflows that
need to reason about generated SQLite bytes without the PHP SQLite extension.

`examples/wordpress-index-leaf-page-assembly.php` extends that fixture path to
a three-page image with a generated `wp_options(option_name)` index leaf page.
The script encodes index cells natively, assembles the index b-tree page,
parses it back through the native reader, and verifies an indexed `siteurl`
lookup through the generated rowid payload without the PHP SQLite extension.

`examples/wordpress-index-interior-page-assembly.php` extends generated index
fixtures to a five-page image whose `wp_options(option_name)` index root is an
interior b-tree page. The script encodes the left child pointer, separator
payload, right-most pointer, and leaf pages natively, then verifies that the
reader walks the generated multi-page index and resolves `siteurl` by rowid
without the PHP SQLite extension.

`examples/wordpress-overflow-page-assembly.php` assembles a `wp_options` row
whose large `option_value` spills from the table leaf cell to SQLite overflow
pages. The script uses native PHP to split the local payload, write the
overflow next-page pointer chain, and parse the generated database image back
through `wordpressOptions()`.

`examples/wordpress-overflow-page-freelist-reuse.php` assembles a
WordPress-shaped `wp_options` row whose large `option_value` spills onto
non-contiguous reusable overflow pages such as `5 -> 3 -> 7 -> 0` while each
page reserves 12 bytes at the tail. The script verifies the generated
next-page pointers and parses the option back without the PHP SQLite
extension, mapping repair tools that need to plan writes into freed pages
without a full pager yet.

`examples/wordpress-freelist-overflow-repair-plan.php` starts from an actual
SQLite-style freelist trunk page, reads its leaf page pointers and header
counts, chooses reusable pages using SQLite's ordinary freelist allocation
order, receives the mutated header/trunk page images from
`planPageAllocation()`, writes a large `wp_options.option_value` overflow
chain into those pages, and verifies the repaired image through the native
reader.

`examples/wordpress-free-obsolete-overflow-pages.php` models the opposite
repair direction: rewrite a large `wp_options` row down to a small inline
value, return the old overflow pages to freelist metadata through
`planPageFreeList()`, and verify both the smaller option row and resulting
freelist allocation order without the PHP SQLite extension.

`examples/wordpress-generated-option-insert-plan.php` starts from a minimal
index-free `wp_options` table image, asks `planWordPressOptionInsert()` for a
bounded generated row insert, applies the returned page images, and parses the
new large option value back through the native reader. This maps WordPress
fixture generation and low-level repair preflight where a tool needs concrete
SQLite page bytes before a full pager, index maintainer, or WAL writer exists.

`examples/wordpress-indexed-generated-option-insert-plan.php` starts from a
minimal `wp_options` table with a single-leaf `option_name` index, asks
`planWordPressOptionInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through `wordpressOptionByIndexedName()`. This maps repair and
fixture generation for common WordPress SQLite images that already have a
simple option-name secondary index.

`examples/wordpress-utf16-option-insert-plan.php` starts from a minimal
UTF-16LE SQLite database image, asks `planWordPressOptionInsert()` for a
bounded generated `blogdescription` row, applies the returned table page
image, and verifies that the option value decodes back to UTF-8. This maps
WordPress SQLite repair/preflight where the file header text encoding is not
UTF-8 but tooling still cannot rely on the SQLite extension.

`examples/wordpress-automatic-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table whose `option_name UNIQUE` constraint is
represented by a `sqlite_autoindex_wp_options_1` schema row with `sql=NULL`.
It asks `planWordPressOptionInsert()` for a bounded generated row insert,
applies the returned table and autoindex page images, and verifies that the
new `home` option is reachable through the inferred automatic index. This maps
WordPress SQLite repair preflight where uniqueness is enforced by a table
constraint rather than an explicit `CREATE INDEX` statement.

`examples/wordpress-partial-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table with a single-leaf
`WHERE option_name IS NOT NULL` partial `option_name` index, asks
`planWordPressOptionInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through the partial index. This maps WordPress SQLite images that
use a safe partial option-name index to exclude malformed `NULL` names while
still covering every normal WordPress option row.

`examples/wordpress-composite-indexed-generated-option-insert-plan.php` starts
from a minimal `wp_options` table with a single-leaf
`autoload, option_name COLLATE NOCASE DESC` composite index, asks
`planWordPressOptionInsert()` for a bounded generated row insert, applies the
returned table and index page images, and verifies that the new `home` option
is reachable through `wordpressOptionByIndexedAutoloadAndName('yes', 'HOME')`.
This maps common WordPress recovery/preload flows that first constrain
autoloaded options and then probe or sort by option name without decoding the
whole table.

`examples/wordpress-composite-indexed-option-replacement-plan.php` starts from
a minimal `wp_options` table with the same composite index, asks
`planWordPressOptionReplace()` to rewrite `siteurl` from `autoload='yes'` to
`autoload='no'`, applies the returned table and index page images, and
verifies that the option is reachable through
`wordpressOptionByIndexedAutoloadAndName('no', 'SITEURL')`. This maps
WordPress repair tools that disable autoload for heavy options while keeping a
preload-oriented composite index consistent.

`examples/wordpress-multipage-composite-indexed-option-replacement-plan.php`
starts from a `wp_options` table with a two-level
`autoload, option_name` secondary index, asks `planWordPressOptionReplace()`
to rewrite `siteurl` from `autoload='yes'` to `autoload='no'`, applies the
returned table and two leaf-index page images, and verifies that the
`index-interior` root still resolves the row through
`wordpressOptionByIndexedAutoloadAndName('no', 'siteurl')`. This maps larger
WordPress option tables where repair tooling must update preload indexes
without collapsing the index tree or invoking the SQLite extension.

`examples/wordpress-index-split-option-insert-plan.php` starts from a
`wp_options` table with a two-level `option_name` secondary index whose
right-most leaf is full for the native page assembler. It asks
`planWordPressOptionInsert()` for a generated option row, applies the returned
header/table/root/leaf page images, and verifies that the existing
`index-interior` root now has a promoted divider plus a newly allocated leaf
while the inserted option is reachable through
`wordpressOptionByIndexedName()`. This maps larger WordPress SQLite images
where repair tooling must insert generated options without the SQLite
extension and without leaving a secondary index stale when the target leaf
splits but the parent can stay at the same depth.

`examples/wordpress-index-root-split-option-insert-plan.php` starts from a
`wp_options` table with a full single-leaf `option_name` secondary index. It
asks `planWordPressOptionInsert()` for a generated option row, applies the
returned header/table/root/new-leaf page images, and verifies that the
original index root page has grown into an `index-interior` page whose two new
leaf children keep the inserted option reachable through
`wordpressOptionByIndexedName()`. This maps small-to-medium WordPress SQLite
images where a repair or fixture-generation insert crosses the first b-tree
depth boundary without the SQLite extension.

`examples/wordpress-index-parent-root-split-option-insert-plan.php` starts
from a larger `wp_options` table with a full two-level `option_name`
secondary index whose right-most leaf and index-interior root are both full.
It asks `planWordPressOptionInsert()` for a generated option row, applies the
returned header/table/root/leaf/new-interior page images, and verifies that
the original root has grown into a higher-level `index-interior` page over
two newly allocated interior pages while the inserted option remains reachable
through `wordpressOptionByIndexedName()`. This maps repair tooling that must
insert a generated option into a larger SQLite-backed WordPress database when
the secondary index crosses a deeper b-tree boundary.

`examples/wordpress-composite-index-parent-root-split-option-insert-plan.php`
starts from a larger `wp_options` table with a full two-level
`autoload, option_name` secondary index whose right-most leaf and
index-interior root are both full. It asks `planWordPressOptionInsert()` for a
generated autoloaded option row, applies the returned
header/table/root/leaf/new-interior page images, and verifies that the grown
composite index still resolves the row through
`wordpressOptionByIndexedAutoloadAndName('yes', $optionName)`. This maps
preload-oriented WordPress SQLite images where repair tooling must add a
generated option without stale composite indexes or the SQLite extension.

`examples/wordpress-nonroot-index-parent-split-option-insert-plan.php` starts
from a larger `wp_options` table with a three-level `autoload, option_name`
secondary index. It asks `planWordPressOptionInsert()` for a generated
autoloaded option row whose target leaf is full and whose non-root
index-interior parent is also full. The example applies the returned
header/table/root/parent/leaf/new-parent page images and verifies that the
root absorbs the promoted parent divider while the inserted option remains
reachable through `wordpressOptionByIndexedAutoloadAndName('yes',
$optionName)`. This maps large WordPress SQLite fallback databases where a
repair preflight must add a generated option without stale composite indexes
and without invoking the SQLite extension.

`examples/wordpress-index-split-option-replacement-plan.php` starts from a
`wp_options` table with a two-level `autoload, option_name` secondary index
whose target `autoload='no'` leaf is full. It asks
`planWordPressOptionReplace()` to rewrite an existing option from
`autoload='yes'` to `autoload='no'`, applies the returned
header/table/root/source-leaf/split-leaf page images, and verifies that the
replaced option is reachable through
`wordpressOptionByIndexedAutoloadAndName('no', $optionName)`. This maps larger
WordPress repair flows that disable autoload for a heavy option while keeping
a preload-oriented composite index consistent through a same-depth leaf split.

`examples/wordpress-composite-index-parent-root-split-option-replacement-plan.php`
starts from a larger `wp_options` table with a full two-level
`autoload, option_name` secondary index. It rewrites an existing option from
`autoload='yes'` to `autoload='no'`, where the destination composite-index
leaf and the index-interior root both have to split. The example applies the
returned header/table/source-leaf/destination-leaf/root/new-interior page
images and verifies that the rewritten option is reachable through
`wordpressOptionByIndexedAutoloadAndName('no', $optionName)`. This maps
preload repair tools that must turn off autoload for a heavy option in a
larger SQLite-backed WordPress database without leaving the composite index
stale and without invoking the SQLite extension.

`examples/wordpress-index-root-collapse-option-replacement-plan.php` starts
from a `wp_options` table whose `autoload, option_name` secondary index root
has two leaf children. It rewrites `siteurl` from `autoload='yes'` to
`autoload='no'`, moving the entry into the sibling leaf and emptying the
source leaf. The planner rebuilds the root as an `index-leaf`, returns the
obsolete child pages to SQLite freelist metadata, and verifies that the
rewritten option remains reachable through the composite index. This maps
WordPress repair tooling that disables autoload for a heavy option in a small
two-level secondary index without leaving orphaned b-tree child pages.

`examples/wordpress-index-redistribute-option-replacement-plan.php` starts
from a `wp_options` table whose `autoload, option_name` secondary index root
has three child leaves. It rewrites a long cached option from
`autoload='yes'` to `autoload='no'`, leaving the old source leaf underfilled
but non-empty. The planner redistributes that source leaf with its adjacent
sibling, updates the parent divider, inserts the moved key into the updated
destination leaf, and verifies that the rewritten option remains reachable
through the composite index. This maps larger WordPress repair tooling that
disables autoload for heavy options without leaving a sparsely filled
secondary-index page behind.

`examples/wordpress-multipage-table-option-replacement-plan.php` starts from
a `wp_options` table whose root is a table-interior page over two table leaf
pages. It asks `planWordPressOptionReplace()` to rewrite the `blogname`
option in the right leaf, applies the returned page image, and verifies that
only page 4 changed while the page-2 table root remains `table-interior`. This
maps larger WordPress SQLite fallback/repair tools that need to change a
single option below an interior table root without the SQLite extension and
before full pager/journal support exists.

`examples/wordpress-table-root-split-option-replacement-plan.php` starts from
a small `wp_options` table whose root is still a single table leaf. It asks
`planWordPressOptionReplace()` for a larger `blogname` rewrite, applies the
returned header/root/new-leaf page images, and verifies that page 2 has grown
into a `table-interior` root over split leaf pages 3 and 4 while every option
row remains readable in rowid order. This maps small WordPress SQLite
databases that cross the first table b-tree depth boundary during a repair or
migration preflight without the SQLite extension.

`examples/wordpress-table-leaf-split-option-replacement-plan.php` starts from
a `wp_options` table whose root is a table-interior page and whose left child
leaf becomes too full after a larger `blogname` replacement. It asks
`planWordPressOptionReplace()` for the rewrite, applies the returned
header/root/old-leaf/new-leaf page images, and verifies that the root now has
two separator cells while all option rows remain readable in rowid order. This
maps WordPress repair tooling that must expand a stored option below a
multi-page table root without the SQLite extension and without silently
corrupting table b-tree separators.

`examples/wordpress-nonroot-table-split-option-replacement-plan.php` starts
from a three-level `wp_options` table b-tree. It replaces `blogname` with a
larger value that splits a leaf under a non-root table-interior parent,
applies the returned header/lower-parent/old-leaf/new-leaf page images, and
verifies that the root separator remains unchanged while the lower parent now
points at the split leaves. This maps larger WordPress SQLite fallback
databases where repair preflight must update one option without forcing a
whole table rewrite.

`examples/wordpress-table-parent-root-split-option-replacement-plan.php`
starts from a `wp_options` table whose table-interior root is full. It
replaces `blogname` with a larger value that splits the right-most leaf and
then grows the full root into two lower table-interior parent pages under a
new one-cell root. The example applies the returned header/root/old-leaf/
new-leaf/new-parent page images and verifies that the rewritten option remains
readable by rowid. This maps large WordPress SQLite fallback databases where
a repair preflight crosses a deeper table b-tree balance boundary without
requiring the SQLite extension.

`examples/wordpress-replace-obsolete-overflow-option.php` starts from a
large `wp_options` value stored across overflow pages, asks
`planWordPressOptionReplace()` for a bounded same-row replacement, applies the
returned table/header/freelist page images, and verifies that the smaller row
is readable while the obsolete overflow chain is now available for future
allocation. This maps cache/transient cleanup and migration repair tools that
need to shrink option rows safely before broader pager, index, or WAL support
exists.

`examples/wordpress-replace-large-overflow-option.php` starts from a large
`wp_options` value, replaces it with a larger overflow-backed value, applies
the returned page images, and verifies both the new overflow chain and the
freelist containing the obsolete pages. This maps WordPress migration and
preload repair tools that need to rewrite large serialized/JSON option
payloads without the SQLite extension while preserving SQLite's allocate-new,
free-old update order.

`examples/wordpress-pointer-map-diagnostics.php` starts from a WordPress-shaped
auto-vacuum SQLite database with a pointer-map page, a `wp_options` root page,
a child b-tree page, an overflow chain, and a free page. It prints the
root/free/btree/overflow pointer-map entries while still reading the
`siteurl` option through the native table reader. This maps repair preflights
that must recognize auto-vacuum metadata before moving, freeing, or reusing
pages in a WordPress SQLite fallback database.

`examples/wordpress-pointer-map-mutation-plan.php` starts from a
WordPress-shaped auto-vacuum SQLite database with a pointer-map page,
`wp_options` b-tree pages, and an overflow chain. It asks the native free-page
planner to release an obsolete overflow page, applies the returned
header/pointer-map/freelist-trunk page images, and verifies that the freed
page's pointer-map entry is now `free-page` while `siteurl` remains readable.
This maps repair preflight for auto-vacuum databases where page moves or
future overflow reuse must not leave stale pointer-map parent references.

`examples/wordpress-autovacuum-overflow-option-insert-plan.php` starts from a
WordPress-shaped auto-vacuum SQLite database and inserts a large
`theme_mods_twentyfive` option that spills to a three-page overflow chain. It
applies the returned header/pointer-map/table/overflow page images, verifies
that the first overflow page points back to the `wp_options` b-tree page and
that continuation overflow pages point to their previous overflow page, then
reads the inserted option through the native table reader. This maps repair
and migration preflight for large theme-mod or cache options on hosts where
the SQLite extension is unavailable and stale auto-vacuum pointer-map entries
would make later page moves unsafe.

`examples/wordpress-autovacuum-overflow-option-replacement-plan.php` starts
from a WordPress-shaped auto-vacuum SQLite database with an existing large
`theme_mods_twentyfive` option stored on overflow pages. It asks
`planWordPressOptionReplace()` to rewrite the option to a larger value,
applies the returned header/pointer-map/table/freelist/overflow page images,
verifies that obsolete overflow pages are now `free-page` entries, verifies
that the new overflow chain carries `first-overflow-page` and `overflow-page`
parent links back to the owning `wp_options` table leaf, and reads the
rewritten option through the native table reader. This maps WordPress repair
preflight where changing a serialized theme-mod/cache option in an
auto-vacuum SQLite database must not leave stale pointer-map owners behind.

`examples/wordpress-autovacuum-table-root-split-option-replacement-plan.php`
starts from a WordPress-shaped auto-vacuum SQLite database whose `wp_options`
table is still a single root leaf. It rewrites `blogname` to a larger value,
applies the returned header/pointer-map/table page images, verifies that the
root grew into a table-interior page, and checks that the new child leaf pages
are `btree-page` pointer-map entries owned by the `wp_options` root. This maps
WordPress repair preflight where changing one larger option must keep
auto-vacuum b-tree parent ownership valid even before broader journaling or
SQL execution support exists.

`examples/wordpress-secure-delete-obsolete-overflow-pages.php` starts from a
WordPress-shaped SQLite database where a large `wp_options` row stores private
cache data on overflow pages. It rewrites the option to a small inline value
with secure-delete planning enabled, applies the returned header/table/freelist
page images, verifies that obsolete overflow pages are on the freelist, and
checks that the obsolete overflow page inserted as a freelist leaf has been
zeroed. This maps repair preflight for sites that require deleted option
payload fragments to be cleared before those pages are reused.

`examples/wordpress-index-merge-option-replacement-plan.php` starts from a
multi-page `wp_options(autoload, option_name)` secondary index where changing a
large cached option from `autoload='yes'` to `autoload='no'` underfills the old
source leaf and leaves too few cells for a legal two-leaf redistribution. It
asks `planWordPressOptionReplace()` for the rewrite, applies the returned
header/table/root/leaf/freelist page images, and verifies that the obsolete
index leaf is now page 6 on the freelist while the rewritten option is
reachable through the composite index. This maps WordPress cache or migration
repair tools that need to change autoload state without leaving an invalid
sparse secondary-index page behind.

`examples/wordpress-nonroot-index-merge-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index with a root
interior page, a lower interior parent, and five leaf children. It changes an
option from `autoload='yes'` to `autoload='no'`, merges the underfilled source
leaf with its adjacent sibling below that non-root parent, removes the parent
divider, moves the lower parent's right-most pointer, and verifies that the
obsolete leaf is now on the freelist while the rewritten option is reachable
through the composite index. This maps larger WordPress SQLite fallback
databases where autoload repair must maintain a deeper secondary index without
waiting for general SQL UPDATE, journaling, or WAL support.

`examples/wordpress-index-parent-collapse-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index where the
source-leaf merge also underfills the lower index-interior parent below a
two-child root. It changes an option from `autoload='yes'` to `autoload='no'`,
merges the old source leaf with its sibling, collapses the underfilled parent
and sibling parent into the root, frees the obsolete leaf and interior pages,
and verifies that the rewritten option is still reachable through the
composite index. This maps larger WordPress SQLite fallback databases where
autoload repair crosses one more b-tree balancing boundary but still does not
require a general SQL engine.

`examples/wordpress-index-parent-merge-option-replacement-plan.php` starts
from a deeper `wp_options(autoload, option_name)` secondary index where the
root has more than two child parents. It changes an option from
`autoload='yes'` to `autoload='no'`, merges the old source leaf with its
sibling, merges the now-underfilled lower parent with an adjacent interior
sibling, removes the root divider while keeping the root at the same height,
frees the obsolete leaf and interior parent, and verifies that the rewritten
option is still reachable through the composite index. This maps larger
WordPress SQLite fallback databases where autoload repair crosses a non-root
parent underflow boundary but the index still has sibling parent pages that
can absorb the merge without requiring a full SQL engine.

## Next Task

Broaden non-root composite-index parent redistribution when adjacent
interior-parent merge does not fit, then broaden cell-level FAST secure-delete,
journaling, and WAL behavior beyond page-image preflight.

## Current-Base Rebase-Prep: `json_group_array()`/`json_group_object()` Option Summary Scenario

Native JSON aggregation now includes a bounded SQLite `json_group_array(X)`/`json_group_object(NAME,VALUE)` row boundary for ordered input rows, SQL NULLs, booleans, JSON subtype fragments, JSONB BLOB values, empty groups, text labels, and malformed raw BLOB rejection. The example `examples/wordpress-json-aggregate-option-summary.php` checks copied `wp_options` rows and produces local-only aggregate JSON summaries that can be reviewed before import without requiring the SQLite extension.

## `jsonb_group_array()`/`jsonb_group_object()` Option Summary Scenario

Native JSON aggregation now also includes a bounded SQLite
`jsonb_group_array(X)`/`jsonb_group_object(NAME,VALUE)` SQL result-type
dispatch boundary. The example
`examples/wordpress-json-aggregate-option-summary.php` checks copied
`wp_options` rows and reports text JSON aggregate summaries plus decoded/hex
JSONB aggregate outputs for copied option values, JSON subtype fragments,
JSONB option blobs, booleans, and NULLs. This gives WordPress import and
repair tooling a local-only way to preserve JSONB fixture typing for aggregate
diagnostics without requiring the SQLite extension.

## `json_insert()`/`json_set()`/`json_replace()` Mutation Dispatch Scenario

Native JSON mutation now includes a bounded SQLite SQL result-type boundary for
`json_insert()`, `json_set()`, `json_replace()`, and their `jsonb_*` variants.
The updated `examples/wordpress-jsonb-mutate-option-field.php` script can
preflight copied `wp_options` JSON option values with text JSON results or
JSONB blob results, preserving SQLite's distinction between ordinary SQL
scalar values and JSON subtype/JSONB embedded fragments without requiring the
SQLite extension.

## `json_array_insert()`/`jsonb_array_insert()` Array Insert Dispatch Scenario

Native JSON array insertion now includes a bounded SQLite SQL result-type
boundary for `json_array_insert()` and `jsonb_array_insert()`. The updated
`examples/wordpress-jsonb-array-insert-option-field.php` script can preflight
copied `wp_options` JSON option arrays or postmeta migration queues with text
JSON results or JSONB blob results, preserving SQLite's array-index,
reverse-index append, missing-array creation, non-array no-op, and JSON
subtype/JSONB embedded-fragment boundaries without requiring the SQLite
extension. The latest isolated finisher exercises uppercase SQL-style
argument-vector dispatch through the same example, preserving the local
WordPress path while matching SQLite's case-insensitive function-name
boundary.

## `json_type()`/`json_array_length()` Inspection Dispatch Scenario

Native JSON inspection now includes a bounded SQLite SQL function dispatch
boundary for `json_type(X[,P])` and `json_array_length(X[,P])`. The updated
`examples/wordpress-json-inspection-preflight.php` script can preflight copied
`wp_options` JSON option values using the same function-name dispatch that SQL
callers expect, including strict JSON, JSON5, cast text BLOBs, JSONB blobs,
SQL NULL, missing paths, non-array scalar length `0`, and JSON type-name
results without requiring the SQLite extension.

## `json_valid()` Validity Dispatch Scenario

Native JSON validity now includes a bounded SQLite SQL function dispatch
boundary for `json_valid(X[,FLAGS])`. The updated
`examples/wordpress-json-validity-preflight.php` script can preflight copied
`wp_options` option values using the same function-name dispatch SQL callers
expect, including strict JSON, JSON5, cast text BLOBs, JSONB blobs, SQL NULL
input, nullable `FLAGS`, and combined flag checks without requiring the SQLite
extension.

## `json_error_position()` Diagnostic Dispatch Scenario

Native JSON diagnostics now include a bounded SQLite SQL function dispatch
boundary for `json_error_position(X)`. The updated
`examples/wordpress-json-error-position-preflight.php` script can preflight
copied `wp_options` option values using the same function-name dispatch SQL
callers expect, including JSON5 text, malformed copied text, cast text BLOBs,
JSONB blobs, superficial-only JSONB blobs, and SQL NULL input without
requiring the SQLite extension.

## JSON Constructor Dispatch Scenario

Native JSON constructors now include a bounded SQLite SQL function dispatch
boundary for `json_array()`, `json_object()`, `jsonb_array()`, and
`jsonb_object()`. The updated
`examples/wordpress-json-constructor-option-diagnostics.php` script can
preflight copied `wp_options` migration diagnostics with text JSON or decoded
JSONB review output, preserving SQLite's distinction between ordinary SQL
values, JSON subtype fragments, JSONB BLOB fragments, raw BLOB rejection, odd
`json_object()` arity, and invalid constructor function names.

Status delta 2026-05-25 isolated micro-slice: added constructor SQL-dispatch
helpers, focused tests, and the WordPress smoke update. Focused verification is
recorded in `lane-status.json` after local checks. Blocker: no hydrated
upstream cache exists in this isolated worktree, so no fresh SQLite testfixture
run was performed; this slice reuses prior `json101.test` and `subtype1.test`
constructor evidence. Next task: integrator acceptance, then one additional
bounded libsqlite behavior slice with its own evidence. Dependency closure: no
new support component is needed; the slice reuses existing lane-local JSON
constructor, JSON subtype, JSONB, and BLOB support and counts no shared
support-library progress.

## `json()`/`jsonb()` Canonical Dispatch Scenario

Native JSON canonicalization now includes a bounded SQLite SQL result-type
boundary for `json()` and `jsonb()`. The updated
`examples/wordpress-json-canonical-option-preflight.php` script can preflight
copied `wp_options` JSON option values with canonical text JSON or decoded
JSONB review output, preserving SQLite's distinction between text JSON
results and JSONB blob results for strict JSON, JSON5, cast text BLOBs, JSONB
BLOBs, SQL NULL values, malformed JSON, and raw BLOB rejection without
requiring the SQLite extension.

Status delta 2026-05-25 isolated micro-slice: added canonical json/jsonb
SQL-dispatch helper, focused tests, and the WordPress smoke update. Latest
priority-refill 2026-05-25T16:13Z keeps that accepted behavior and adds
case-insensitive `JSON`/`JSONB` lookup plus one-argument SQL vector dispatch;
the WordPress smoke now exercises uppercase argument-vector dispatch for
copied option values. Focused verification is recorded in `lane-status.json`
after local checks. Blocker: no hydrated upstream cache exists in this
isolated worktree, so no fresh SQLite testfixture run was performed; this
slice reuses prior `json101.test`, `json501.test`, `json107.test`, and
`jsonb01.test` canonicalization evidence. Next task: integrator acceptance,
then one additional bounded libsqlite behavior slice with its own evidence.
Dependency closure: no new support component is needed; the slice reuses
existing lane-local JSON canonicalizer, JSON5 parser, JSONB, and BLOB support
and counts no shared support-library progress.

## Focused Native Mapping: `json_each()`/`json_tree()` Hidden Columns

Date: 2026-05-25

This isolated micro-slice maps the bounded SQLite JSON table-valued hidden-column boundary for `json_each(X[,P])` and `json_tree(X[,P])`. Native row arrays now include the hidden `json` column as the original text/JSONB argument and the hidden `root` column as the effective root path used for the scan, while preserving the accepted visible `key`, `value`, `type`, `atom`, `id`, `parent`, `fullkey`, and `path` columns.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1/JSONB table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test jsonb01.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-tree-option-settings.php
php lanes/libsqlite/examples/wordpress-json-tree-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: syntax checks passed, the WordPress example reported recursive root/plugin/rules rows with hidden `json`/`root` summaries, focused PHP passed 1 selected test file, 2116 assertions, and 0 failures, and final diff/json checks are recorded in `lane-status.json`. This worker did not start the root aggregate harness because root verification was not assigned.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and SQL value typing support; it counts no shared support-library progress.

## Focused Native Mapping: `json_each()` Case-Insensitive SQL Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options `json_each()` expansion smoke to exercise uppercase `JSON_EACH` SQL dispatch. That keeps plugin settings review paths aligned with SQLite's case-insensitive function-name behavior while preserving the accepted strict JSON, JSON5 text, JSONB blob, SQL NULL, hidden `json`/`root`, and invalid-function coverage.

Focused upstream runner:

The detached worktree for this isolated lane did not contain the hydrated `.upstream-cache/libsqlite` checkout, so no new upstream `testfixture` run was started. This slice reuses prior focused JSON1 table-valued evidence for the same upstream behavior cluster:

```sh
json101.test json102.test json501.test json107.test
```

Prior applicable runner evidence remains the complete SQLite `veryquick` run: 1235 scripts, 329670 tests, and 0 errors.

Native PHP evidence:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/tests/SQLiteHeaderTest.php
php -l lanes/libsqlite/examples/wordpress-json-each-option-settings.php
php lanes/libsqlite/examples/wordpress-json-each-option-settings.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
git diff --check -- lanes/libsqlite
```

Result: recorded in `lane-status.json` after focused verification. Root aggregate harness was not assigned for this isolated micro-slice.

Dependency closure: no new support component is needed. The slice reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support; it counts no shared support-library progress.

## Focused Native Mapping: Table-Valued JSON Case-Insensitive Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options recursive JSON expansion smoke to exercise uppercase `JSON_TREE` SQL dispatch and tightens both table-valued dispatch helpers to explicit case-insensitive comparison. That keeps plugin settings review paths aligned with SQLite's case-insensitive function-name behavior while preserving accepted `json_each()`/`json_tree()` rows, hidden `json`/`root` values, strict JSON, JSON5 text, JSONB blob, SQL NULL, and invalid-function coverage.

Focused verification is recorded in `lane-status.json`. Dependency closure: no new support component is needed; this reuses existing lane-local JSON path, JSON5, JSONB, BLOB, canonical encoding, and table-valued row support and counts no shared support-library progress.

## Focused Native Mapping: Table-Valued JSON Argument-Vector Dispatch

Date: 2026-05-25

This isolated micro-slice updates the local wp_options `json_each()` and
`json_tree()` smokes to exercise uppercase SQL function names through
one-or-two argument vectors. The smokes now dispatch `$.plugin` and
`$.plugin.rules` via the SQL-style vector helpers while preserving accepted
strict JSON, SQLite JSON5, JSONB blob, SQL NULL, hidden `json`/`root`, and
case-insensitive function-name coverage.

Focused verification is recorded in `lane-status.json`. Dependency closure:
no new support component is needed; this reuses existing lane-local JSON path,
JSON5, JSONB, BLOB, canonical encoding, and table-valued row support and
counts no shared support-library progress.

## `json_error_position()` Argument-Vector Dispatch Scenario

Native JSON diagnostics now include the one-argument SQL-style vector dispatch
boundary for `json_error_position(X)`. The updated
`examples/wordpress-json-error-position-preflight.php` script exercises
uppercase `JSON_ERROR_POSITION` dispatch over copied `wp_options` option
values, including JSON5 text, malformed copied settings, cast text BLOBs,
JSONB blobs, superficial-only JSONB blobs, and SQL NULL input without
requiring the SQLite extension.

## `json_valid()` Argument-Vector Dispatch Scenario

Native JSON validity now includes the one-or-two argument SQL-style vector
dispatch boundary for `json_valid(X[,FLAGS])`. The updated
`examples/wordpress-json-validity-preflight.php` script exercises uppercase
`JSON_VALID` dispatch over copied `wp_options` option values, including strict
JSON text, JSON5 text, cast text BLOBs, copied JSONB blobs, SQL NULL input,
nullable `FLAGS` rejection, and combined JSON5-or-superficial-JSONB flag
checks without requiring the SQLite extension.

## JSON Constructor Argument-Vector Dispatch Scenario

Native JSON constructor diagnostics now include SQL-style vector dispatch for
`json_array()`, `jsonb_array()`, `json_object()`, and `jsonb_object()`. The
updated `examples/wordpress-json-constructor-option-diagnostics.php` script
exercises uppercase `JSON_ARRAY`, `JSON_OBJECT`, `JSONB_ARRAY`, and
`JSONB_OBJECT` dispatch over copied `wp_options` migration diagnostics,
including JSON subtype passthrough, JSONB queue blobs, SQL NULL array members,
JSONB result decoding, and raw BLOB rejection without requiring the SQLite
extension.
## WAL Frame Option Diagnostics

Native WAL inspection now includes a bounded read-only frame parser for
WordPress recovery/import tooling. The new
`examples/wordpress-wal-option-frame-diagnostics.php` script builds a WAL
fixture with committed schema and `wp_options` page images, extracts page
images through the last commit frame, and reads pending `siteurl`/`blogname`
options without requiring the SQLite extension.

This is intentionally not a full checkpoint or recovery engine yet. WAL
checksum validation, WAL-index/shared-memory state, checkpoint writing,
rollback journals, and savepoint behavior remain separate slices.
