# LightningCSS Target Prefixing: text-decoration-skip-ink Boundaries

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260531T184642Z`

Base accepted HEAD: `0c0eec061390da3a2185ec8623476b5865dd4a49`

Upstream source truth:

- Upstream cache HEAD: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` maps `text-decoration-skip-ink` as a WebKit-prefixed property with `VendorPrefix`.
- `src/properties/prefix_handler.rs` includes `TextDecorationSkipInk` in the generated prefix handler list.
- `src/prefixes.rs` maps `Feature::TextDecorationSkipInk` to WebKit for iOS Safari `>= 8.0` and Safari `>= 7.1` through `12.0`.
- `src/lib.rs::test_text_decoration` preserves minified prefixed and unprefixed `text-decoration-skip-ink` declarations.

Red-first evidence:

```sh
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets(".foo { text-decoration-skip-ink: all; }", ["safari"=>12]), "\n";'
```

Before this patch, Safari 12 output remained `.foo{text-decoration-skip-ink:all}` and missed upstream's required `-webkit-text-decoration-skip-ink` boundary.

Implemented behavior:

- Added `textDecorationSkipInkNeedsWebkit` target option for Safari 7.1 through 12.0 and iOS Safari 8+.
- Reused the existing vendor-prefixed declaration group rewriting path for `text-decoration-skip-ink`.
- Added focused tests for Safari 7.0, 7.1, 12.0, 12.1, iOS Safari 8, iOS Safari 17, and stale prefixed declaration pruning for modern Safari.
- Added `examples/wordpress-text-decoration-skip-ink-prefixer.php` to model shared-hosting WordPress underline CSS output without Node/WASM.

Non-overlap:

- This does not repeat earlier text prefixing for `text-size-adjust`, `hyphens`, `tab-size`, `text-align-last`, `text-overflow`, `box-decoration-break`, or sticky positioning.
- This does not repeat accepted mask, clip-path, border-image, background-clip, or text-decoration-thickness target-prefixing slices.

Verification:

```sh
php -l lanes/lightningcss/src/TransitionPrefixer.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-text-decoration-skip-ink-prefixer.php
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
php tools/run-tests.php lanes/lightningcss/tests
php lanes/lightningcss/examples/wordpress-text-decoration-skip-ink-prefixer.php
git diff --check -- lanes/lightningcss
```

Results:

- `TransitionPrefixerTest.php`: `1 test files, 483 assertions, 0 failures`.
- Full LightningCSS lane: `13 test files, 3148 assertions, 0 failures`.
- Example smoke exited 0 and printed Safari 12 / Safari 12.1 / iOS 17 expected outputs.

Dependency closure:

- No new support component is needed. The slice reuses the existing target version parser and vendor-prefixed declaration group rewriter.
