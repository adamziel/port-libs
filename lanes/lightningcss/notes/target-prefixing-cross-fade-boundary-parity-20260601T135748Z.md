# Target Prefixing Cross-Fade Boundary Parity

Source truth: pinned upstream `parcel-bundler/lightningcss` commit
`22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs`
`Feature::CrossFade`.

Implemented slice:

- Added target-aware WebKit `cross-fade()` value prefixing for image-valued
  declarations: `background`, `background-image`, `border-image`,
  `border-image-source`, `list-style`, `list-style-image`, `mask`,
  `mask-image`, `-webkit-mask`, and `-webkit-mask-image`.
- Mirrored upstream browser boundaries: Chrome 17+, Android 4.4+, Edge 79+,
  iOS Safari 5 through 9.3, Opera 15+, Safari 5.1 through 9.1, and Samsung 4+.
- Added stale prefixed-value cleanup when the modern unprefixed `cross-fade()`
  value is present and the current target does not need the WebKit fallback.
- Added matching `@supports` declaration-condition insertion and stale
  condition pruning.
- Added a WordPress cover/media example smoke for cross-fade hero backgrounds.

Verification evidence:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`: pass.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`: pass.
- `php -l lanes/lightningcss/examples/wordpress-cross-fade-prefixer.php`: pass.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`:
  1 test file, 1339 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-cross-fade-prefixer.php --self-test`:
  pass.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files,
  8181 assertions, 0 failures.

Dependency closure: no new support component is needed. The slice reuses the
existing CSS minifier, declaration parser, target option normalization,
function-value scanner helpers, and supports-condition parser.

Non-overlap: this does not repeat accepted `image-set`, linear-gradient,
print-color-adjust, image-rendering, supports declaration property-prefix,
selector, media-query resolution, CSS Modules, source-map, bundle/import graph,
CSSOM, or custom at-rule clusters. It only ports the upstream CrossFade target
boundary value-prefix behavior.
