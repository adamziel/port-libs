# LightningCSS logical text-align target fallback parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T202907Z`

Accepted base: `29362e0d6ada0a9ddb4cefdc699cee6add41d488`

Pinned upstream source truth: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Upstream evidence

- `src/lib.rs::test_text_align` has six `prefix_test` helper rows for `text-align:start` and `text-align:end`: Safari 2 LTR/RTL fallback selectors, Safari 14 preservation, descendant selector insertion, pseudo-element insertion, and pseudo-class insertion.
- `src/properties/text.rs` lowers `TextAlign::Start` and `TextAlign::End` through `context.add_logical_rule(...)` when `Feature::LogicalTextAlign` needs fallback.
- `src/compat.rs` marks `LogicalTextAlign` unsupported for Chrome `< 18`, Edge `< 79`, Firefox `< 4`, Opera `< 14`, Safari `< 3.1`, iOS Safari `< 2`, Samsung `< 1`, Android `< 37`, and IE.
- Source reads used:
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/properties/text.rs`
  - `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/compat.rs`

## Implementation

- `TransitionPrefixer` now emits modern `:not(:is(...))` and `:is(...)` LTR/RTL fallback rules for non-important `text-align:start` and `text-align:end` when the target matrix requires `LogicalTextAlign` fallback.
- The fallback keeps modern targets as logical values and inserts the selector suffix before `:before`, `:after`, `:first-letter`, and `:first-line` pseudo-elements.
- `wordpress-text-compat-prefixer.php` now covers a navigation block `text-align:start` path with legacy fallback output and modern preservation.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-text-compat-prefixer.php`: no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 633 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-text-compat-prefixer.php --self-test`: exits 0
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 4158 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`: exits 0

## Coverage delta

- Local PHP pass/assertion count moves `4150 -> 4158`.
- Conservative mapped upstream denominator moves `2078 -> 2084 / 3532`.
- Counted mapped cases: six upstream `src/lib.rs::test_text_align` `prefix_test` helper rows.
- Additional Chrome 17/18 assertions validate the `Feature::LogicalTextAlign` compat boundary table and are not counted as separate upstream helper rows.

## Non-overlap and dependency closure

- This slice does not overlap the stale lane rework note for custom-media import-tail scanner conflicts.
- This slice does not repeat accepted logical inset/border fallback work; it targets only logical `text-align` start/end fallback selectors.
- No new support component is needed. Existing PHP selector splitting and target-feature option handling are reused.
- Full upstream Rust/Node/WASM runners were not executed for this isolated micro-slice.
