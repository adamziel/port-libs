# real-upstream-corpus-pragma-schema-dynamic-20260530T224329Z-0

Added `SQLiteRealUpstreamPragmaSchemaDynamicListTableValuedTest.php`, a real upstream PRAGMA/schema dynamic corpus batch for list-PRAGMA table-valued metadata.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`
  - `pragma6-1.0` through `pragma6-1.2`: generated-column schema image plus `integrity_check`/`quick_check` completion.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - table-valued PRAGMA function behavior for `pragma_*` rowsets.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.2` through `pragma-6.5`: schema metadata remains row-shaped and joinable.

Behavior covered:

- `pragma_function_list()` table-valued rows match direct `PRAGMA function_list` rows, including dynamic scalar/window function metadata, encodings, arity, and flags.
- `pragma_module_list()` table-valued rows match direct `PRAGMA module_list` rows and preserve sorted module names.
- `pragma_collation_list()` table-valued rows match direct `PRAGMA collation_list` rows and preserve sequence numbers plus uppercased collation names.
- `pragma_pragma_list()` table-valued rows match direct `PRAGMA pragma_list` rows and include list/schema PRAGMA names.
- Virtual-table schema introspection for `pragma_function_list`, `pragma_module_list`, and `pragma_pragma_list` remains available through `PRAGMA table_info`.

Focused growth:

- New focused TestRunner PASS cases: 1,002.
- New focused behavior assertions: 5,259.
- `lane-status.json` `phpPass`: `991889 -> 992891`.
- Mapped denominator coverage unchanged at `1589 / 1589`; this is new PHP behavior coverage for already hydrated upstream PRAGMA/schema source files, not a new denominator row.

Non-overlap:

- This does not repeat accepted PRAGMA schema first/sixth-thousand table-info/index/FK/schema batches, direct wide-batch list PRAGMA calls, schema3/cache invalidation, schema4 name-collision, schema5 legacy constraint grammar, schema6 layout equivalence, or `pragma6` generated-column integrity completion.
- This slice owns the list-PRAGMA table-valued spelling and virtual-table schema rows for `pragma_function_list()`, `pragma_module_list()`, `pragma_collation_list()`, and `pragma_pragma_list()`.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local PRAGMA schema catalog and existing row-shaped PRAGMA metadata primitives.
