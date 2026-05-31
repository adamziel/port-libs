# real-upstream-corpus-expression-affinity-dynamic-20260531T045647Z-0

Added `SQLiteRealUpstreamTypes3TextAffinityDynamicTest.php` as an additive real upstream expression/affinity corpus batch.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`
- `types3-1.1` through `types3-2.6`: manifest storage classes for bound values and selected values.
- `types3-3.1` through `types3-3.5`: TEXT-affinity primary-key comparisons must use the string representation of values with integer/real dual representation.

Focused coverage:

- 700 integer dynamic cases derived from `types3-3.1` through `types3-3.3`.
- 550 real dynamic cases derived from `types3-3.4` and `types3-3.5`.
- 6,267 behavior assertions in the focused file.

Non-overlap:

- This does not repeat accepted `types2.test` indexed rowset coverage, `affinity2.test` comparison matrices, `affinity3.test` REAL view affinity preservation, expression boolean-truth batches, explicit-float-text batches, CAST/LIKE/GLOB source-neutral cleanup, Unicode GLOB, or SQL expression `ORDER BY`.
- It claims focused PASS-line/assertion growth only, not mapped denominator growth.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP manifest storage-class classification and expression-affinity comparison behavior.
