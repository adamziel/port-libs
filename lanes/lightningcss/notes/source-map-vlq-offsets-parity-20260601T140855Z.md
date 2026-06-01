# Source Map VLQ Offsets Parity - Directive Tokenization

## Source Truth

- Upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Bundler path: LightningCSS consumes `Stylesheet::source_map_url(0)` for imported CSS before remapping inline input source maps.
- Parser source truth: `cssparser-0.37.0/src/tokenizer.rs` accepts only comment contents that start exactly with `# sourceMappingURL=` or `@ sourceMappingURL=`, then stops the URL at the first space, tab, form feed, carriage return, or newline.
- Upstream tokenizer tests cover the old `@` directive, trailing whitespace, "last directive wins", whitespace after `=`, empty URLs, leading-space rejection, and spaced-`=` rejection.

## Native Delta

- `CssBundler::sourceMapUrlFromComment()` now matches the upstream exact-prefix tokenizer instead of trimming leading whitespace and accepting `sourceMappingURL =`.
- Added focused bundler coverage proving that:
  - `/*@ sourceMappingURL=data:... */` still remaps an imported generated CSS source through its input source map.
  - `/*   # sourceMappingURL=data:... */` remains a generated CSS source instead of suppressing it.
  - `/*# sourceMappingURL = data:... */` remains a generated CSS source instead of suppressing it.
  - `/*# sourceMappingURL=  data:... */` extracts an empty URL and leaves the generated CSS source intact.
- Added the same WordPress block-theme import graph smoke to `wordpress-bundle-import-graph.php`.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - passed, `1 test files, 786 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed, including `source-map-directive-tokenization: matched`.
- `php tools/run-tests.php lanes/lightningcss/tests` - passed, `13 test files, 8181 assertions, 0 failures`.

## Status Delta

- `phpPass` moves `8160 -> 8181`.
- Conservative mapped coverage remains `2393 / 3532`; this deepens the already represented bundle/import graph and source-map clusters rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP `CssBundler`, `CssMinifier`, and `SourceMap` plumbing.

## Non-Overlap

This does not repeat the accepted media-query resolution x-unit serialization slice, source-map VLQ table import/offset cases, inline input map remapping, duplicate generated-column offsets, or generated-offset import graph coverage. It is specifically the upstream cssparser directive-tokenization boundary that decides whether an imported stylesheet has an inline input source map.
