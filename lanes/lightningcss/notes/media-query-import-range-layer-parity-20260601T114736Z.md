# LightningCSS Media Query Import Range Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T114736Z`

Upstream source truth:

- Pinned upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss` at `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/rules/import.rs` serializes `ImportRule` media tails through `self.media.to_css(dest)`, so target-conditioned media query printing applies to import tails after `layer(...)` and `supports(...)` modifiers.
- `src/media_query.rs` lowers media range and interval syntax during media query serialization when printer targets require `MediaRangeSyntax` or `MediaIntervalSyntax` fallback.

Behavior implemented:

- `TransitionPrefixer::prefixForTargets()` now rewrites top-level `@import` media tails before block-rule rewriting.
- The import tail scanner consumes the import URL/string plus optional `layer`, `layer(...)`, and `supports(...)` modifiers, then applies the same range syntax fallback and `dppx`/`x` resolution unit conversion used for block `@media` prelude lowering.
- The implementation intentionally does not add vendor-prefixed resolution clones to import tails. Upstream `ImportRule` prints the media list but does not run the block `MediaRule::minify()` resolution-prefix transform.

Red-first evidence:

Before this slice, this current-base probe kept modern range syntax in import tails:

```bash
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets("@import \"blocks/query.css\" layer(theme.blocks) (width >= 240px); @layer theme.blocks { .wp-block-query { color: yellow; } }", ["firefox"=>60]), PHP_EOL; echo $p->prefixForTargets("@import \"blocks/query.css\" layer(theme.blocks) (100px <= width <= 200px); @layer theme.blocks { .wp-block-query { color: yellow; } }", ["firefox"=>85]), PHP_EOL;'
```

Pre-fix output preserved `(width>=240px)` and `(100px<=width<=200px)` in the import media tails.

Focused test delta:

- Added `transition prefixer maps upstream import media range tails after layer modifiers` with 7 assertions covering simple ranges, interval ranges, `supports(...)` after `layer(...)`, `url(...)` import sources, include/exclude feature flags, and `dppx` to `x` resolution unit conversion.
- Updated `wordpress-media-range-layer-prefixer.php` self-test with 3 imported block stylesheet scenarios.
- `lane-status.json` `phpPass` moved from `7615` to `7622` for the +7 focused TestRunner assertions. Conservative mapped coverage remains `2374 / 3532`.

Verification:

- `php -l lanes/lightningcss/src/TransitionPrefixer.php` - pass
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` - `1 test files, 1253 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test` - pass
- `git diff --check -- lanes/lightningcss` - pass

Dependency closure:

No new support component is needed. This reuses the existing PHP `MediaQueryParser`, `CssMinifier`, and `TransitionPrefixer` target-option plumbing.

Non-overlap:

This is not another block `@media` range/layer fallback, negated-group fallback, or resolution prefix clone. It is limited to top-level `@import` media tails after layer/supports modifiers, matching the upstream import-rule media serialization path.
