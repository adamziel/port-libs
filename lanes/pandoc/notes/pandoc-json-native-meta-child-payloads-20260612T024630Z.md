# Pandoc JSON/native metadata child payloads

Bead: `plib-7fvf1`
Date: 2026-06-12 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonWriter` now emits `MetaInlines` and `MetaBlocks` children through
the same guarded inline/block writer paths used for normal document content.

This preserves unchanged child constructor payloads when a metadata wrapper is
rebuilt after edits. Edited `MetaInlines` and `MetaBlocks` wrappers now drop
stale wrapper sidecars while keeping compatible source payloads for children
such as `Str`, `Space`, `Code`, `Para`, and `HorizontalRule`.

`NativeWriter` already used the guarded child emitters; the regression covers
both writers from the JSON reader's typed metadata AST.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online
services, live provider tests, or live-service provider tests were invoked.

## Accounting

- `mappedJsonNativeMetaChildPayloadCases`: `+1`
- Focused assertions: `1513`
- Full lane assertions: `69757`

## Verification

- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 1513 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69757 assertions, 0 failures`
