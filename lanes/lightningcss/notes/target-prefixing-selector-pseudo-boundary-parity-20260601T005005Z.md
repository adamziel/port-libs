# LightningCSS target prefixing selector pseudo boundary parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T005005Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Ranges: `src/prefixes.rs` `Feature::PseudoElementSelection`, `PseudoClassPlaceholderShown`, `PseudoClassFullscreen`, `PseudoElementBackdrop`, `PseudoElementFileSelectorButton`, `PseudoClassAutofill`, `PseudoClassReadOnly | PseudoClassReadWrite`, and `PseudoClassAnyLink`.
- Serializer spellings: `src/selector.rs` for `:-webkit-full-screen`, `:-moz-full-screen`, `:-ms-fullscreen`, `::-webkit-file-upload-button`, `::-ms-browse`, `:-webkit-autofill`, `:-moz-read-only`, `:-moz-read-write`, `::-moz-selection`, and `::-webkit-backdrop`.

Implemented:

- Added target-gated selector variants for `::selection`, `:placeholder-shown`, `:fullscreen`, `::backdrop`, `::file-selector-button`, `:autofill`, `:read-only`, `:read-write`, and `:any-link`.
- Added 27 focused browser-boundary assertions to `TransitionPrefixerTest.php`.
- Added `wordpress-selector-pseudo-prefixer.php --self-test` covering editor selection, search placeholder, file block controls, login autofill, cover fullscreen/backdrop, and navigation links.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`; conservative mapped coverage moves `2238 / 3532` to `2265 / 3532`.

Verification:

- Baseline before this slice: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with `1 test files, 814 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with `1 test files, 841 assertions, 0 failures`.
- Full lane-focused: `php tools/run-tests.php lanes/lightningcss/tests` passed with `13 test files, 5162 assertions, 0 failures`.
- Example smoke: `php lanes/lightningcss/examples/wordpress-selector-pseudo-prefixer.php --self-test` exited `0`.

Dependency closure:

- No new support component is needed. This reuses `TransitionPrefixer` target-version routing, selector variant expansion, `CssMinifier`, and existing browser target normalization.

Non-overlap and follow-up:

- This does not repeat the accepted placeholder pseudo-element boundary slice; it covers the adjacent selector pseudo-class/pseudo-element families still missing from the prefixer.
- Follow-up target-prefixing work: stale prefixed selector removal for modern targets and multi-selector-list composition matching upstream `:is()` fallback behavior.

Root harness status: not run - isolated micro-slice.
