# Real Upstream Corpus Window Dynamic Frames

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T172604Z-0`
- Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
    - `window8-1.1.*`: `GROUPS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING`
    - `window8-1.9.*`: `GROUPS BETWEEN 2 PRECEDING AND CURRENT ROW`
    - adjacent generated `GROUPS`, `RANGE`, and `ROWS` boundary families
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
    - generated boundary rows around `UNBOUNDED`, `CURRENT ROW`, numeric
      `PRECEDING` / `FOLLOWING`, and value-function frame behavior
- Ported behavior:
  - Added `SQLiteWindowFunction::valueFrameBetweenValues()` for dynamic
    `BETWEEN` frame boundaries used by `first_value`, `last_value`, and
    `nth_value`.
  - Added focused tests for dynamic `ROWS`, `RANGE`, and `GROUPS` frame shapes,
    aggregate functions, FILTER-after-frame behavior, EXCLUDE modes, text peer
    groups, value functions, and invalid boundary forms.
- Focused assertion count:
  - New direct test: `1 test files / 3559 assertions / 0 failures / 7 selected
    PASS lines`.
  - Window family check: `4 test files / 3750 assertions / 0 failures`.
  - Generic no-domain API guard: `1 test files / 3 assertions / 0 failures`.
- Non-overlap:
  - This does not repeat existing fixed-offset `SQLiteWindowExcludeFilterCorpus`,
    `SQLiteWindowFrameBoundaryCorpus`, or SQL parser `GROUPS`/`RANGE` coverage.
    The new cluster specifically exercises dynamic string frame boundaries and
    value-function frames through the reusable window helper.
- Dependency closure:
  - No new support component is needed. The slice reuses the existing native
    PHP window frame machinery and extends it for value functions.
