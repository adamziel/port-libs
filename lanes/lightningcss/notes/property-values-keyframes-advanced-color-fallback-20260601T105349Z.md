# LightningCSS Keyframes Advanced Color Fallback

Slice: `lightningcss-property-values-color-font-grid-parity-20260601T105349Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/lib.rs::test_custom_properties`.

Implemented behavior:

- Rewrites custom-property advanced colors inside `@keyframes` to an sRGB base plus Lab `@supports` keyframes for Chrome 90 targets.
- Adds display-p3 and Lab support-wrapped keyframes for mixed Chrome 90 / Safari 14 targets.
- Leaves keyframes already inside Lab or display-p3 `@supports` wrappers unchanged.
- Preserves unchanged frame declarations while rewriting only advanced-color declarations.
- Handles the upstream `text-decoration: var(--foo) lab(...)` keyframes fallback case.

Non-overlap:

- This does not change the accepted keyframes target-prefixing slice, CSS Modules keyframe scoping, font-palette color fallback, SVG paint fallback, selector-prefix pruning, or grid/font value minifier clusters.

Verification:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` passed.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-keyframes-advanced-color-fallback.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed: `1 test files, 1237 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-keyframes-advanced-color-fallback.php --self-test` passed: `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` passed: `13 test files, 7473 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` passed.

Dependency closure:

- No new support component is needed. The slice reuses existing PHP target option, declaration parsing, advanced-color fallback, and keyframe prefixing helpers.

Follow-up:

- Full upstream Rust/Node/WASM LightningCSS runners were not executed in this isolated lane.
