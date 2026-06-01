# Target Prefixing Shape Safari Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T145818Z`

Source truth:
- Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/prefixes.rs` maps `Feature::ShapeMargin`, `Feature::ShapeOutside`, and `Feature::ShapeImageThreshold` WebKit prefixes for iOS Safari 8.0-10.0 and Safari 7.1-10.0.

Behavior:
- `TransitionPrefixer` now starts Safari WebKit shape prefixes at 7.1 instead of 7.0.
- Safari 7.0 now keeps unprefixed `shape-outside`, `shape-margin`, and `shape-image-threshold`, removes stale matching `-webkit-shape-*` declarations, and leaves `@supports (shape-outside: ...)` unexpanded.
- Safari 7.1 through 10.0 still get the WebKit declaration and `@supports` alternatives; iOS Safari 8.0 through 10.0 behavior is unchanged.

Red-first evidence:
- Before the source change, this probe over-prefixed Safari 7.0:
  - `php -r 'require "tools/bootstrap.php"; $p = new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".shape{shape-outside:circle();shape-margin:10px;shape-image-threshold:.5}", ["safari" => "7"]), PHP_EOL;'`
  - Output: `.shape{-webkit-shape-outside:circle();shape-outside:circle();-webkit-shape-margin:10px;shape-margin:10px;-webkit-shape-image-threshold:.5;shape-image-threshold:.5}`

Verification:
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 1348 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 8309 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-shape-prefixer.php --self-test` => exit 0
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` => no syntax errors
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` => no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-shape-prefixer.php` => no syntax errors
- `git diff --check -- lanes/lightningcss` => exit 0

Status delta:
- Full lane assertion count moves from `8305` to `8309`.
- Conservative mapped coverage remains `2393 / 3532` because this deepens the existing upstream shape target-prefix cluster rather than adding a new denominator row.

Non-overlap:
- This does not repeat accepted background-clip, SourceMap, CSSOM, CSS Modules, custom at-rule, bundle/import graph, selector, media-query, UI, print-color-adjust, grid, columns, break, scroll snap, clip-path, or viewport target-prefix slices.
- This is limited to the Safari lower-bound edge for WebKit shape target-prefix behavior in upstream `src/prefixes.rs`.

Dependency closure:
- No new support component is needed. This reuses `TransitionPrefixer`, existing target-version routing, support-condition rewriting, stale prefixed declaration cleanup, and the existing WordPress shape example harness.
