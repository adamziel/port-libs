# Pandoc Math TeX Accent Alias Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T044330Z`
Base: `b7207ea8e728f24041eefd971a1a50d4e50c22fc`

## Behavior

Mapped a bounded texmath accent-alias cluster into native MathML without invoking Pandoc, TeX engines, MathJax, KaTeX, or online services:

- over accents: `\dddot`, `\DDDot`, `\ddddot`
- under accents: `\utilde`, `\wideutilde`

The converter now emits semantic `<mover accent="true">` or `<munder accentunder="true">` nodes, preserves escaped source TeX in semantics annotations, and exposes accessibility alt text / intent tokens for triple dot, quadruple dot, and tilde-below accents.

## Source Truth

The bounded source-truth probe checked texmath's Unicode-to-TeX mapping table from `jgm/texmath` and found:

- U+0330 maps to `\utilde` and `\wideutilde`
- U+20DB maps to `\dddot` and `\DDDot`
- U+20DC maps to `\ddddot`

`\widecheck` was intentionally excluded because the primary texmath mapping probe did not show it in the checked source table.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` => `1 test files, 1233 assertions, 0 failures`
- Syntax: `php -l lanes/pandoc/src/MathTexConverter.php` => no syntax errors
- Syntax: `php -l lanes/pandoc/tests/MathTexConverterTest.php` => no syntax errors
- Syntax: `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` => no syntax errors
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` => `1 test files, 1243 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` => `math tex handoff self-test ok`
- JSON validation: `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path . " ok\n"; }'` => both lane JSON files parse
- Diff hygiene: `git diff --check -- lanes/pandoc` => passed

Status delta:

- `phpPass`: `2318 -> 2319`
- mapped denominator: `2718 -> 2719`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 95`

## Dependency Closure

No new support dependency is required. This slice reuses the lane-local PHP TeX tokenizer, bounded accent-command maps, MathML serializer, accessibility text/intent mapper, focused test runner, and WordPress math handoff smoke.

Full upstream Pandoc/texmath runner parity, real TeX engine rendering, MathJax, KaTeX, and browser rendering remain out of scope for this isolated micro-slice.

## Non-Overlap

This slice avoids prior accepted math/TeX coverage for general accent aliases, prime notation, extensible arrows, arrays, color/cancel boxes, AMS environments, equation references, and PDF engine fake-runner diagnostics. It only adds the texmath-backed dot and undertilde accent aliases listed above.
