# real-upstream-corpus-window-functions-dynamic-20260531T021822Z-0

Owned upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test` sections `3.0-3.1`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test` sections `4.0-5.2`

Behavior ported:

- Sparse REAL `RANGE ... PRECEDING` window frames from `windowE.test 3.1`, including the row where a single non-zero value enters the large preceding range.
- Overflow-sensitive `total()` and `sum()` following frames from `windowE.test 4.1`, `4.2`, `5.1`, and `5.2`.
- Dynamic variants extend those same upstream behaviors with shifted sparse row keys, varied hot rows, varied REAL preceding offsets, and integer/REAL tail sums.

Non-overlap:

- This slice does not repeat accepted mixed-type REAL RANGE, `window8` dynamic frame matrix, `window9` collation/filter, `windowB` JSON-object inverse, lead/lag/ntile, or parser-level grouped SELECT window behavior.
- No domain-specific API or fixture surface is introduced.

Dependency closure:

- No new support component is needed. Existing `SQLiteWindowFunction` aggregate frame helpers are reused directly.
