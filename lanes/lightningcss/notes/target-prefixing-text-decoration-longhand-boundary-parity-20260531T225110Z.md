# Text Decoration Longhand Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T225110Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs`.

Upstream boundary:

- `Feature::TextDecoration` emits WebKit prefixes for Safari/iOS `8..26`.
- `Feature::TextDecorationColor | Feature::TextDecorationLine | Feature::TextDecorationStyle` emits WebKit prefixes only for Safari/iOS `8..12`.
- The same longhand feature group emits Mozilla prefixes only for Firefox `6..35`.

Implementation:

- `TransitionPrefixer` now tracks a separate `textDecorationLonghandNeedsWebkit` target option for Safari/iOS `8..12`.
- Text-decoration shorthand prefixing still uses the broader Safari/iOS `8..26` WebKit range.
- Stale `-webkit-text-decoration-line/style/color` longhands are removed for Safari/iOS `12.1+` when matching unprefixed longhands are present.
- Stale `-moz-text-decoration-line/style/color` longhands are removed for Firefox `36+` when matching unprefixed longhands are present.

Verification:

- Red probe before implementation: Safari `12.1`, `16`, and `26` emitted stale `-webkit-text-decoration-line/style/color` longhands.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` -> `1 test files, 708 assertions, 0 failures`.
- Full lane after implementation: `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 4712 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-text-decoration-prefixer.php` exits `0` and checks Safari `12`, `12.1`, `16`, and `27` outputs.
- Syntax and hygiene: `php -l` passes for `TransitionPrefixer.php`, `TransitionPrefixerTest.php`, and `wordpress-text-decoration-prefixer.php`; lane JSON parses; `git diff --check -- lanes/lightningcss` passes.

Status delta:

- Focused assertion growth: `TransitionPrefixerTest.php` moved from the accepted `698` assertion surface to `708`.
- Full lane assertion growth: `4702 -> 4712`.
- Conservative mapped coverage: `2174 / 3532 -> 2177 / 3532`, one row each for `text-decoration-line`, `text-decoration-style`, and `text-decoration-color` longhand target-boundary parity.

Non-overlap and exclusions:

- This slice does not touch source-map null `sourcesContent`, CSSOM declaration priority, CSS Modules escaped pseudo/local-global compose behavior, text-decoration thickness, or text-decoration skip-ink.
- The stale CustomMediaTransformer rework note under the main handoff directory was inspected as lane context. The current accepted manifest already contains the custom-media import-tail/scanner rework, so this slice stays on the assigned target-prefixing boundary.
- Full upstream Rust/Node/WASM runners were not executed for this isolated PHP lane slice.

Dependency closure: no new support component is needed. The patch reuses the native `TransitionPrefixer` target-version normalizer, declaration scanner, text-decoration value normalization, and stale-prefix cleanup flow.
