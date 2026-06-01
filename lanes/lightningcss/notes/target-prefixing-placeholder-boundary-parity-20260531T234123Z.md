# Target Prefixing Placeholder Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T234123Z`

Source truth:
- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_prefixes` includes `.foo::placeholder { color: red }` and expects `::-webkit-input-placeholder`, `::-moz-placeholder`, `::-ms-input-placeholder`, then the modern `::placeholder` selector for Chrome 45, Firefox 45, and IE 11.
- `src/prefixes.rs` `Feature::PseudoElementPlaceholder` maps WebKit prefixes for Android 2.1-4.4.3, Chrome 4-56, iOS Safari 4.3-10, Opera 15-43, Safari 5-10, and Samsung 4-6.2; Mozilla prefixes for Firefox 18-50; Microsoft prefixes for Edge 12-18 and IE 10+.

Implementation:
- `TransitionPrefixer` now adds placeholder selector prefix variants through the existing selector-prefix expansion pipeline.
- Legacy targets emit prefixed placeholder selector rules before the unprefixed rule; modern boundary targets keep only `::placeholder`.
- The slice is intentionally bounded to unprefixed `::placeholder` selector expansion. Adjacent pseudo selector families such as `:read-only`, `:fullscreen`, and `::file-selector-button` remain separate follow-up slices.

Evidence:
- Red-first probe before implementation: `php -r 'require "tools/bootstrap.php"; echo (new PortLibs\LightningCSS\TransitionPrefixer())->prefixForTargets(".foo::placeholder { color: red; }", ["chrome"=>45,"firefox"=>45,"ie"=>11]), PHP_EOL;'` emitted `.foo::placeholder{color:red}`.
- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-placeholder-prefixer.php`: no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`: 1 test file, 784 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files, 4931 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-placeholder-prefixer.php --self-test`: passes.
- `git diff --check -- lanes/lightningcss`: passes.

Status delta:
- `phpPass` moves from 4914 to 4931.
- Conservative mapped coverage moves from 2207 / 3532 to 2208 / 3532.
- Root harness status: not run - isolated micro-slice.

Dependency closure:
- No new support component is needed. This reuses `TransitionPrefixer` target-version routing, selector variant expansion, rule serialization, and the existing minifier/parser path.

Non-overlap:
- This does not touch the stale custom-media rework note for `CustomMediaTransformer.php`, nor accepted selector `:not/:is/:lang/:dir`, text-decoration, length fallback, CSSOM, bundle, source-map, or CSS Modules clusters.
