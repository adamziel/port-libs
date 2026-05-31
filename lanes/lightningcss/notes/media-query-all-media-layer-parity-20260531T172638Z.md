# LightningCSS Media Query All-Type Layer Parity

Slice: `lightningcss-media-query-range-layer-parity-20260531T172638Z`

Base: `44f5a84dd3fb1e975cdd96de7c52fd3736849c68`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted upstream source: `src/media_query.rs` `MediaQuery::to_css`, where `MediaType::All` is omitted when there is no `not`/`only` qualifier and a condition is present.
- The same upstream serializer keeps `not all and (...)` and `only all and (...)` because the qualifier requires the explicit media type.

## Red-First Evidence

Before the patch, this local probe preserved the redundant `all and` prefix:

```bash
php -r 'require "tools/bootstrap.php"; echo (new PortLibs\LightningCSS\CssMinifier())->minify("@layer blocks { @media all and (width >= 600px) { .foo { color: chartreuse } } }"), PHP_EOL;'
```

Observed before implementation:

```text
@layer blocks{@media all and (width>=600px){.foo{color:#7fff00}}}
```

Upstream-compatible output omits the unqualified `all` media type:

```text
@layer blocks{@media (width>=600px){.foo{color:#7fff00}}}
```

## Native Delta

- `MediaQueryParser` now elides an explicit unqualified `all and` media type when serializing a conditional media query.
- Boolean operation wrappers are unwrapped only for this `all` elision path, so `all and ((color) or (hover))` becomes `(color) or (hover)`.
- Qualified media queries remain explicit: `not all and (color)` and `only all and (color)` are preserved.
- Layered block-theme CSS and target fallback prefixing now share the same upstream-compatible prelude before legacy range lowering.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php`
  - Result: `1 test files, 146 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - Result: `1 test files, 426 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 2707 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test`
  - Result: exited `0`
- `php -l lanes/lightningcss/src/MediaQueryParser.php && php -l lanes/lightningcss/tests/MediaQueryParserTest.php && php -l lanes/lightningcss/tests/TransitionPrefixerTest.php && php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php`
  - Result: no syntax errors
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json", "lanes/lightningcss/lane-status.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`
  - Result: both JSON files decode successfully
- `git diff --check -- lanes/lightningcss`
  - Result: exited `0`

Root harness status: not run - isolated micro-slice.

## Status Delta

- Full LightningCSS PHP evidence: `2696 -> 2707 pass / 0 fail`.
- Conservative mapped coverage remains `1571 / 3532`; this deepens the represented media-query serializer/range-layer cluster rather than claiming a new denominator row.

## Non-Overlap

This avoids accepted parent-relative bundler import resolution, returned custom at-rule AST serialization, generated-only source-map offsets, parenthesized negated media ranges, box-sizing prefix boundaries, media range include/exclude flags, typed/unknown/equality range fallback, invalid media range validation, resolution prefixing, resolution `x` unit serialization, cascade-layer merge/import validation, custom-media scanner behavior, CSSOM, CSS Modules, bundler, color/font/grid/property-value, and target-prefix slices.

## Dependency Closure

No new support component is needed. The slice reuses the native `MediaQueryParser`, `CssMinifier`, `TransitionPrefixer`, and lane-local examples/tests. No upstream binary, browser service, parser generator, or external CSS engine is required.

## Next Task

Continue with non-overlapping LightningCSS media-query parser/minifier, CSSOM, CSS Modules, SourceMap, target-prefix, property-value/font/grid/color, bundler, and custom-at-rule parity. No current blocker was introduced by this slice.
