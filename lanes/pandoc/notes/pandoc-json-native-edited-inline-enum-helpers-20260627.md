# Pandoc JSON/native Edited Inline Enum Helpers

Slice: `pandoc-json-native-edited-inline-enum-helpers-20260627`

This bounded JSON/native AST constructor-completeness slice preserves tagged
nullary helper constructor provenance when inline enum semantics are edited and
rewritten through `PandocJsonWriter` and `NativeWriter`.

Covered helpers:

- `QuoteType` helpers on `Quoted` inlines.
- `MathType` helpers on `Math` inlines.
- `CitationMode` helpers inside citation records.

When an edited AST changes the semantic enum, the writer now regenerates the
helper tag (`DoubleQuote`, `InlineMath`, `NormalCitation`, etc.) while keeping
the helper provenance sidecars. Stale `c` payload fields are dropped on
regenerated nullary helpers, matching the existing canonicalization contract.

Verification:

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - Result: the new edited inline enum helper regression passes.
  - Full focused file remains baseline-red: 1 file, 5,991 assertions,
    11 unrelated failures.

Accounting:

- `phpPass`: `460 -> 461`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2306 -> 2307`
- `mappedJsonNativeInlineEnumHelperEditCases`: `0 -> 1`

No Pandoc executable, JSON filters, Cabal/Haskell runners, TeX engines, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.
