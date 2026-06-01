## LightningCSS property values currentColor relative color parity - 2026-06-01T15:03Z

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260601T150306Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream scenario: `src/lib.rs` `test_relative_color`, unresolved `lch(from currentColor l c sin(h))`

Behavior added:

- `CssMinifierTest.php` now has a focused assertion that LightningCSS preserves unresolved `currentColor` relative LCH instead of incorrectly resolving or simplifying it.
- `wordpress-color-value-minifier.php` now includes the same unresolved relative color in a block-cover color smoke to keep the user-visible example aligned.

Verification:

- `php -l lanes/lightningcss/tests/CssMinifierTest.php`
- `php -l lanes/lightningcss/examples/wordpress-color-value-minifier.php`
- `php lanes/lightningcss/examples/wordpress-color-value-minifier.php --self-test`
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php`
- `php tools/run-tests.php lanes/lightningcss/tests`
- `git diff --check -- lanes/lightningcss`

Result:

- Full LightningCSS PHP lane: `13 test files / 8328 assertions / 0 failures`.
- `phpPass` moves `8327 -> 8328`.
- Mapped coverage remains `2393 / 3532`; this is a deeper assertion in an already represented relative-color upstream function.

Non-overlap:

- Avoided the already accepted CSSOM list-style/counter, color-adjust alias, grid shorthand, font oblique, font-family dedupe, relative srgb Lab/LCH resolution, JSON/source-map/bundle/import, and target-prefix clusters.

Dependency closure:

- No new support component is needed. The existing `CssMinifier` tokenizer/color path already preserves unresolved origin-dependent relative colors; this patch makes the upstream behavior countable and covered by the existing example smoke.

Next task:

- Continue property-value parity on non-overlapping upstream color/font/grid cases, especially unresolved relative-color forms or grid/font value interactions that are not already represented in focused PHP tests.
