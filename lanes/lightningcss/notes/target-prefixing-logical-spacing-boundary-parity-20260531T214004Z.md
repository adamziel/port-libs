# Target Prefixing Logical Spacing Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T214004Z`

Base: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

Upstream source truth:

- Pinned upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Files inspected:
  - `src/lib.rs::test_margin`
  - `src/lib.rs::test_padding`
  - `src/prefixes.rs` `Feature::MarginInlineStart`, `MarginBlockStart`, `PaddingInlineStart`, and `PaddingBlockStart`

Behavior implemented:

- `TransitionPrefixer` now lowers logical `margin-*` and `padding-*` spacing properties for old target browsers using the upstream LTR/RTL physical fallback shape.
- Inline spacing fallback follows upstream browser boundaries: Safari 3.1-12.0, iOS Safari 3.2-12.0, Chrome 4-68, Android 2.1-4.4.3, Opera 15-55, Samsung 4-9, and Firefox 3-40.
- Block spacing fallback follows the same upstream set except Firefox, matching `src/prefixes.rs`.
- Unsupported logical spacing shorthands expand to start/end longhands for mid-boundary targets such as Safari 13.
- `LogicalProperties` include/exclude flags force or suppress the spacing fallback consistently with existing logical prefix behavior.

Red-first evidence:

- Before implementation, `prefixForTargets(".foo { margin-inline-start: 2px; padding-block-end: 4px; }", ["safari" => 8])` returned `.foo{margin-inline-start:2px;padding-block-end:4px}`.
- The focused tests now assert the upstream physical fallback and shorthand boundary behavior.

Mapped coverage:

- Conservative denominator movement: `2145 / 3532` -> `2155 / 3532`.
- Counted checks cover Safari 8 physical fallback for margin and padding inline/block spacing, Safari 13 shorthand-to-longhand expansion, Chrome 68/69 and Firefox 40/41 browser boundaries, and include/exclude flags.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 674 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 4476 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-logical-spacing-prefixes.php`
  - exits `0`

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP selector variant, declaration parsing, and top-level whitespace splitting helpers.

Non-overlap:

- This does not alter accepted logical border, logical inset, logical text-align, display flex, transition-property, or grid minifier/CSSOM clusters.
