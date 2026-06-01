# CSSOM Grid Auto-Flow Read/Write Parity

Slice: `lightningcss-cssom-declaration-read-write-parity-20260601T020500Z`

Base accepted HEAD: `dc8bb5cac377111467dc403c9b9c75704db62cd4`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

- `src/declaration.rs` updates existing shorthand declarations during CSSOM `set`, and splits shorthand declarations into remaining longhands during CSSOM `remove`.
- `src/properties/grid.rs` defines the `grid` shorthand as `grid-template-rows`, `grid-template-columns`, `grid-template-areas`, `grid-auto-rows`, `grid-auto-columns`, and `grid-auto-flow`.
- `src/properties/grid.rs` serializes `grid: auto-flow ... / ...` and `grid: ... / auto-flow ...` forms for implicit row/column auto-flow grids, while `GridAutoFlow::to_css` prints non-minified dense row flow as `row dense`.

Implementation:

- `DeclarationBlock` now expands `grid` shorthand auto-flow forms into CSSOM template and auto-placement longhands for `getProperty()`.
- `setProperty()` rewrites valid existing `grid` shorthand values when `grid-template-*` or `grid-auto-*` longhands are updated, instead of appending duplicate longhands.
- `removeProperty()` splits `grid` shorthand values into the remaining grid longhands when a contained template or auto-placement longhand is removed.
- Added `wordpress-grid-auto-flow-cssom.php` to exercise block-layout custom property spacing through read/write/remove CSSOM operations.

Status delta:

- Focused `DeclarationBlockTest.php` increased from 886 to 908 assertions.
- Full LightningCSS lane increased from 5451 to 5473 assertions.
- Manifest mapped coverage remains conservative at `2297 / 3532` because this deepens the already represented DeclarationBlock CSSOM cluster.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` -> `1 test files, 908 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 5473 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-grid-auto-flow-cssom.php --self-test` -> `OK`
- Syntax and diff checks are recorded in the final handoff.

Dependency closure:

No new support component is required. This reuses the native PHP declaration parser, top-level token splitting, grid track serializer, and existing CSSOM shorthand mutation paths.

Non-overlap:

This does not repeat accepted CSSOM transform, all-keyword, logical-size, background, flex, gap, grid-template, or grid-area behavior. It targets only the missing `grid` auto-flow shorthand component read/write/remove parity. The stale May 25 `CustomMediaTransformer` rework note was inspected and is unrelated to this DeclarationBlock grid auto-flow slice.
