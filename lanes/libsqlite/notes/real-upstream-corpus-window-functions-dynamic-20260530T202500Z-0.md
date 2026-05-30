# Real Upstream Window Functions Dynamic Slice

- Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
    sections `1.1-1.6`, `5.1`, `5.5`, and `8.1-8.3`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
    sections `4.1`, `5.1`, and `5.2`.
- Behavior added:
  - `WINDOW name AS ()` now expands as an empty window definition instead of
    being rejected.
  - Named window references in final `ORDER BY` expressions are expanded before
    expression planning, matching `window6.test` reuse of `RANK() OVER w`.
  - The focused PHP test now includes mapped identifier variants from
    `window6.test`, following-only ROWS frames, reused named windows in
    `ORDER BY`, shorthand and explicit `ROWS 2 PRECEDING`, and large numeric
    `total()` / `sum()` window frames from `windowE.test`.
- Focused assertion movement:
  - Before: `SQLiteRealUpstreamWindowFunctionsDynamicTest.php` passed with
    `60` assertions.
  - After: the same focused file passes with `95` assertions.
  - Focused delta: `+35` real upstream behavior assertions / PASS lines.
- Non-overlap:
  - This slice does not add the already accepted `window7` RANGE/GROUPS
    coverage, window3/window4/window5/windowA/windowB/windowC/windowD matrix,
    JSON table, VFS, WAL, B-tree, PRAGMA, trigger/FK, UPSERT, or expression
    affinity corpus surfaces.
- Dependency closure:
  - No new support component is required. The patch reuses the existing
    `SQLiteSelectSql` window parser/executor and focused PHP test harness.
- Follow-up:
  - The remaining `window6.test` keyword-as-column cases that use an unquoted
    column named `over`, plus recursive CTE bodies with compound recursive
    arms, still need a larger parser slice before they can be admitted safely.
