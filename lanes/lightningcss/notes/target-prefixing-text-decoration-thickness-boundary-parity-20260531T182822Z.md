# Text Decoration Thickness Target Fallback Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T182822Z`

Accepted base: `1d7de15e4e85a2b8dbfd1c80922d2921091d0371`

Upstream source truth:

- Pinned LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/lib.rs::test_text_decoration` includes four `prefix_test` helper cases for `text-decoration-thickness` percentage fallback and shorthand fallback boundaries.
- `src/properties/text.rs` lowers unsupported percentage thickness values to `calc(1em / n)`-style length fallbacks.
- `src/compat.rs` defines `TextDecorationThicknessPercent` support at Chrome/Edge/Android 87, Firefox 79, Opera 62, Safari/iOS 17.4, Samsung 14, and `TextDecorationThicknessShorthand` support at Chrome/Edge/Android 87, Firefox 79, Opera 62, Safari/iOS 26.2, Samsung 14.

Implemented behavior:

- `TransitionPrefixer` now splits unsupported `text-decoration` shorthand thickness values into a following `text-decoration-thickness` declaration.
- Legacy percentage thickness targets now lower `10%` to `calc(1em / 10)` for both shorthand-split and standalone `text-decoration-thickness`.
- Boundary tests preserve native output for modern Chrome/Firefox/Safari targets.
- Added a WordPress block-content underline example with `--self-test` coverage.

Red-first evidence:

- Before implementation, `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` failed on the new Safari 15 shorthand fallback assertion: actual output kept `-webkit-text-decoration:underline 10px;text-decoration:underline 10px`.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: `1 test files, 466 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 3070 assertions, 0 failures`
- PHP lint, example self-test, and `git diff --check -- lanes/lightningcss` were run before handoff; see final response for exact commands.

Status delta:

- `phpPass`: `3060 -> 3070`
- Conservative mapped denominator: `1684 -> 1688`
- Mapped upstream cases: four `src/lib.rs::test_text_decoration` text-decoration-thickness prefix helper cases.

Non-overlap:

- This slice does not repeat accepted text-decoration line/style/color prefixing, advanced-color fallback layering, or border-image target-prefix boundaries. It is limited to `text-decoration-thickness` shorthand and percentage target fallback parity.

Dependency closure:

- No new support component is needed. The implementation reuses the existing target normalization, declaration parsing, text-decoration value parsing, and example harness.
