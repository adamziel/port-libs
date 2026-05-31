# Media Query Comment Token Layer Parity - 2026-05-31T23:11:25Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream behavior: `src/media_query.rs::MediaQuery::parse_with_options` consumes media type and qualifier tokens, then requires an `and` identifier token before a following condition. The upstream cssparser token stream treats block comments as token separators, so `screen/* comment */and (width >= 240px)` parses as `screen and (width >= 240px)`, not as a `screenand(...)` function.

## Red-First Evidence

Before this patch, the native PHP minifier stripped comments before media parsing and merged adjacent identifiers:

```text
php -r 'require "tools/bootstrap.php"; use PortLibs\LightningCSS\CssMinifier; try { echo (new CssMinifier())->minify("@layer blocks { @media screen/*x*/and (width >= 240px){.foo{color:yellow}} }"), PHP_EOL; } catch (Throwable $e) { echo "ERR ", $e->getMessage(), PHP_EOL; }'
ERR Unknown media query condition function: screenand(width>=240px)
```

## Native Delta

- `CssMinifier::stripComments()` now preserves a single separator when deleting a block comment would merge adjacent CSS tokens that normally need whitespace.
- Focused media/layer tests cover `screen/*...*/and`, `only/*...*/screen/*...*/and`, `not/*...*/screen/*...*/and`, `all/*...*/and`, and comment-separated `or` around a range query inside `@layer`.
- The WordPress media layer example now self-tests comment-separated media qualifiers and range queries in block CSS.

## Verification

```text
php -l lanes/lightningcss/src/CssMinifier.php
No syntax errors detected in lanes/lightningcss/src/CssMinifier.php

php -l lanes/lightningcss/tests/MediaQueryParserTest.php
No syntax errors detected in lanes/lightningcss/tests/MediaQueryParserTest.php

php -l lanes/lightningcss/examples/wordpress-media-layer-minifier.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-media-layer-minifier.php

php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php
1 test files, 332 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-layer-minifier.php --self-test
Exited 0 and printed the expected layered media minification output.

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 4795 assertions, 0 failures

php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); } echo "json ok\n";'
json ok

git diff --check -- lanes/lightningcss
Exited 0 with no whitespace errors.
```

## Status Delta

- Full lane PHP evidence moves from `4790` to `4795` assertions, `0` failures.
- Mapped upstream denominator is unchanged; this deepens the existing media-query parser/minifier cluster rather than claiming a new upstream file row.

## Non-Overlap

This slice does not duplicate the accepted media-query conjunction fallback, escaped media identifier, x-resolution, target media fallback, invalid condition, or custom-media import-tail work. The stale custom-media import-tail rework note was reviewed and left untouched because this patch is bounded to comment token separation before media query parsing.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP comment scanner, media query parser, and minifier pipeline.
