# Pandoc Charset/Unicode Width Core Current-Base Slice

Micro-slice: `pandoc-charset-unicode-width-core-current-base-20260609T050538Z`

Base accepted HEAD: `5d02a10932dbbd350c989c1902aead80ac5c366a`

## Behavior

Added `UnicodeText::lineBreakOpportunities()` as a bounded PHP audit helper for the same Unicode display-width and separator rules used by `wrapByDisplayWidth()`.

The helper reports:

- soft break opportunities for zero-width space, soft hyphen, Unicode spaces, tabs, and Tibetan tsheg break-after separators;
- hard break opportunities for normalized line feeds plus Unicode line and paragraph separators;
- protected non-break separators for no-break space, narrow no-break space, figure space, and word joiner;
- byte offsets and display columns under narrow or wide East Asian ambiguous-width policy;
- CRLF/CR line-ending normalization metadata.

The WordPress charset handoff example now exposes break-opportunity and protected-separator rows for review packets without invoking external conversion tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `1 test files, 1432 assertions, 1 failures`

Failure: `Call to undefined method PortLibs\Pandoc\UnicodeText::lineBreakOpportunities()`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UnicodeTextTest.php`

Result: `1 test files, 1453 assertions, 0 failures`

Example smoke:

`php lanes/pandoc/examples/wordpress-charset-unicode-handoff.php --self-test`

Result: `charset unicode handoff self-test ok`

## Status Delta

Focused assertion delta: `+21`

PHP PASS delta: `+1`

Mapped charset/Unicode-width cases: `9 -> 10`

`lanes/pandoc/lane-status.json` updated to `phpPass: 2347`.

`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` updated to `benchmarkDenominator.mapped: 2742`, `mappedCharsetUnicodeWidthCoreCases: 10`, and `charsetUnicodeWidthCoreAssertions: 86`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `UnicodeText` repair, line-ending normalization, grapheme display-width, separator wrapping, and WordPress charset handoff support. Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, TeX/PDF engines, browser renderers, online services, live provider tests, or live-service provider tests.

Root harness not run: isolated micro-slice.
