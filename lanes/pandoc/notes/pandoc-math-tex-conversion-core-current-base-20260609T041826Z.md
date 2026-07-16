# Pandoc math TeX conversion current-base slice 20260609T041826Z

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T041826Z`
Base accepted HEAD: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

## Behavior

This slice maps the bounded reciprocal harpoon extensible-arrow aliases
`xrightleftharpoons` and `xleftrightharpoons` into the existing native PHP
`MathTexConverter` extensible-arrow path. The aliases now emit stretchy MathML
operators with `mover` or `munderover` depending on the optional lower label,
preserve braced and single-token upper labels, keep escaped source TeX
annotations, and participate in the existing accessibility alttext and intent
metadata.

The WordPress math handoff example now includes the same reciprocal harpoon
audit expression so review packets keep both the original TeX span and native
MathML output without invoking an external math renderer.

## Red-first evidence

Baseline focused test command before this patch:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

Result:

`1 test files, 1211 assertions, 0 failures`

A transient probe before the implementation showed both commands leaking as
literal command-name identifier tokens, for example
`<mi>\xrightleftharpoons</mi>` and `<mi>\xleftrightharpoons</mi>`, instead of
using the extensible-arrow MathML path.

## Final verification

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

Result:

`1 test files, 1221 assertions, 0 failures`

`php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`

Result:

`math tex handoff self-test ok`

PHP lint:

- `php -l lanes/pandoc/src/MathTexConverter.php` - no syntax errors
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` - no syntax errors

Metadata and whitespace checks:

- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " ok\n"; }'` - both JSON files valid
- `git diff --check -- lanes/pandoc` - no output

## Status delta

- `lanes/pandoc/lane-status.json` `phpPass`: `2286 -> 2287`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2687 -> 2688`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 95`
- Focused test assertion count: `1211 -> 1221`

## Dependency closure

No new support component is needed. This reuses the existing native PHP
`MathTexConverter` parser, MathML serializer, source TeX annotations,
accessibility metadata, Markdown/WordPress math handoff path, and focused PHP
test harness. No Pandoc, texmath, Cabal/Haskell runner, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, Word, LibreOffice, zip/unzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Non-overlap and follow-up

This does not repeat the prior current-base extensible-arrow work for
`xlongequal`, hook arrows, twohead arrows, one-way harpoon arrows, unbraced
arrow labels, delimiter handling, matrix/array environments, colors, comments,
or macro handling.

A useful follow-up is another bounded texmath command-table gap with focused
PHP tests, such as remaining extensible accent aliases or relation aliases that
still fall back to command-name identifier tokens.
