# LightningCSS Grid Template Area Override Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T163543Z`

Accepted base: `6b3dbcd9ba83baf454581e5cfdd21849ee67aa00`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '22540,23580p'`.
- Mapped 4 additional focused upstream `src/lib.rs::test_grid` shorthand override cases: `grid` plus later `grid-template-areas`, `grid` plus later `grid-template-rows` and `grid-template-areas`, `grid-template` plus later `grid-template-areas`, and `grid` plus later `grid-template-areas` over a three-row track list. The slice also guards the upstream auto-flow case that remains split rather than composed.

Native PHP delta:

- `CssMinifier` now composes later `grid-template-areas` declarations into compatible existing `grid` and `grid-template` shorthands when the shorthand has concrete row/column tracks.
- A later `grid-template-rows` override participates in that shorthand composition.
- Shorthands containing `auto-flow` are left split so auto-placement behavior continues to match upstream.
- `wordpress-grid-value-minifier.php` now models a featured query block layout whose authored grid shorthand, rows, and areas compact into one `grid` declaration without Node.

Evidence:

- Red-first current-base probe before implementation produced `.grid-shorthand-areas{grid:auto/1fr 3fr;grid-template-areas:".content."}` for the upstream shorthand override case instead of `.grid-shorthand-areas{grid:".content."/1fr 3fr}`.
- `php -l lanes/lightningcss/src/CssMinifier.php && php -l lanes/lightningcss/tests/CssMinifierTest.php && php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 947 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 2319 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php` -> exits 0 and emits the expected minified block grid CSS.
- `git diff --check -- lanes/lightningcss` -> exits 0.

Non-overlap:

- Does not repeat accepted lch/oklch color-mix normalization, accepted grid auto-flow/placement longhand composition, accepted direct grid/grid-template shorthand value minification, accepted CSSOM grid read/write/remove behavior, or accepted font-family/font-shorthand/font-face/font-palette/font-feature slices.
- This handoff is only the remaining concrete shorthand plus later `grid-template-areas` override behavior from `src/lib.rs::test_grid`.

Dependency closure:

- No new support component is needed. This reuses the bounded native `CssMinifier` declaration-entry composer, top-level splitters, grid value minifiers, and grid-template serializer.

Root harness status: not run - isolated micro-slice.
