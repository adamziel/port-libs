# Pandoc JSON Maybe Short Caption Constructor Slice

Bead: `plib-434f3`
Date: 2026-06-10 UTC
Scope: `lanes/pandoc` JSON/native AST constructor completeness.

## Change

`PandocJsonReader` now accepts `Maybe ShortCaption` wrapper constructors in supported `Figure` and `Table` `Caption` payloads:

- `Just (ShortCaption [...])` hydrates shared `shortCaptionInlines` and `shortCaption`.
- `Nothing` is preserved as an explicit no-short-caption case.

The writer still emits the existing canonical bounded caption shape after read, so generated JSON remains stable while imported constructor variants no longer fail before shared AST handoff.

## Non-overlap

This slice does not alter package readers, ZIP/OPC, DOCX, EPUB, ODF, CSL/BibTeX, XML/HTML DOM, Markdown/plain/CommonMark/wiki/roff/media-bag behavior, or diagnostic-only paths. Direct-format parity accounting is unchanged.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 file / 408 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files / 60995 assertions / 0 failures

No Pandoc, JSON filter, Cabal/Haskell runner, office suite, TeX/browser engine, zip/unzip command, Jupyter, Node tooling, external validator, online service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass`: 2997 -> 2998
- `phpFail`: 0
