# Target Prefixing Logical Size Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T003402Z`

Source truth:

- Upstream pinned commit: `parcel-bundler/lightningcss` `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine reads used `git show` because the upstream working cache has unrelated local edits.
- `src/lib.rs::test_size` first three `prefix_test` helpers:
  - Safari 8 lowers `block-size`, `inline-size`, `min-block-size`, and `min-inline-size` to physical size properties, serialized as `height`, `min-height`, `width`, and `min-width`.
  - Safari 14 preserves the logical size declarations.
  - Safari 8 also lowers the same logical size properties when values are `var(--size)`.
- `src/compat.rs::Feature::LogicalSize` supplies the target boundary: Chrome/Android 57, Edge 79, Firefox 41, Opera 43, Safari 12.1, iOS 12.2, Samsung 7, and no IE support.

Implementation:

- Added a `logicalSizeNeedsFallback` target flag independent from the broader logical inset fallback.
- Logical size declarations now lower to physical size properties for unsupported browser targets, with contiguous logical-size groups serialized in upstream physical order: block axis first (`height`, `min-height`, `max-height`), then inline axis (`width`, `min-width`, `max-width`).
- Existing intrinsic sizing keyword prefixing now uses the logical size feature boundary rather than the logical inset boundary, preserving accepted keyword prefix behavior while matching upstream logical size support.
- Added `wordpress-logical-size-prefixer.php` as a cover-block smoke for Safari 12 physical fallback and Safari 12.1 logical preservation.

Verification:

- Red probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $p = new \PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { block-size: 25px; inline-size: 25px; min-block-size: 25px; min-inline-size: 25px; }", ["safari" => 8]), PHP_EOL;'`
  - Output before patch: `.foo{block-size:25px;inline-size:25px;min-block-size:25px;min-inline-size:25px}`
- Focused test:
  - `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result after patch: `1 test files, 827 assertions, 0 failures`
- Full lane focused run:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result after patch: `13 test files, 5148 assertions, 0 failures`
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-logical-size-prefixer.php`
  - Result: exits 0 and prints Safari 12 physical fallback plus Safari 12.1 logical output.
- Syntax and lane checks:
  - `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `php -l lanes/lightningcss/examples/wordpress-logical-size-prefixer.php`
  - `git diff --check -- lanes/lightningcss`
  - Results: all passed.

Status delta:

- Focused `TransitionPrefixerTest.php` assertions increased from 814 to 827.
- Conservative mapped coverage increases by 3, from 2238 / 3532 to 2241 / 3532, for the three upstream `src/lib.rs::test_size` logical-size `prefix_test` helpers.
- Full upstream Rust/Node/WASM runners were not executed for this isolated lane slice.

Non-overlap:

- Avoids accepted target-prefix clusters for animation timeline, placeholder pseudo-elements, background clip, intrinsic sizing keywords, media-query resolution fallbacks, and text-decoration longhands.
- A stale handoff rework note exists for a May 25 custom-media import-tail conflict; it does not name this session or target-prefix logical-size behavior, so this slice treated it as unrelated historical context.

Dependency closure:

- No new support component is needed. The existing PHP `TransitionPrefixer` target-version and declaration-rewrite helpers are sufficient for this upstream behavior cluster.
