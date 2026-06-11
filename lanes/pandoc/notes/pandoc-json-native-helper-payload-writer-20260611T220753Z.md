# Pandoc JSON/native helper payload writer 20260611T220753Z

Bead: `plib-whjof`
Post-rebase base: `895143aff`

## Scope

`PandocJsonWriter` now preserves source-tagged helper constructor payload
shapes when they still match the shared AST value being written.

Covered JSON/native helper payloads:

- ordered-list style and delimiter enum payloads
- quoted and math inline enum payloads
- citation mode enum payloads
- table column alignment and width helper payloads
- table body `RowHeadColumns`
- table cell alignment, `RowSpan`, and `ColSpan`

The guard is value-aware: edited shared AST fields regenerate canonical helper
constructors instead of reusing stale source payloads. Legacy five-slot tables
and old two-slot target inlines continue to normalize through the existing
Pandoc JSON writer path.

Direct-format parity accounting is not affected; this is JSON/native AST
constructor-provenance output coverage only.

## Verification

- Focused: `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1093 assertions, 0 failures`
- Full lane: `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66575 assertions, 0 failures`

No Pandoc, JSON filters, Cabal/Haskell runners, office suites, TeX/browser
engines, zip/unzip, external validators, online services, live provider tests,
or live-service provider tests were invoked.
