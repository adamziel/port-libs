# Direct ODT Style Duplicate Diagnostics

Slice: `pandoc-odt-style-duplicate-diagnostics-current-base-20260610T101728Z`
Base accepted HEAD: `9e4780afa`
Date: 2026-06-10 UTC

## Behavior

- `OdtReader` now preserves duplicate named style-catalog declarations as
  native import-report diagnostics instead of silently overwriting them.
- The direct ODT style report covers duplicates across:
  - `style:style`
  - `style:font-face`
  - `text:list-style`
  - OpenDocument data styles
  - `table:table-template`
  - `style:page-layout`
  - `style:master-page`
- Replacement declarations still win for import behavior, matching the prior
  bounded reader behavior, while reviewer metadata records the duplicate name
  and available previous/replacement family or element provenance.

## Evidence

- Syntax checks:
  - `php -l lanes/pandoc/src/OdtReader.php`
  - `php -l lanes/pandoc/tests/OdtReaderTest.php`
- Focused ODT reader verification:
  - `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - Result: `1 test files, 145 assertions, 0 failures`
- ODT handoff self-tests:
  - `php lanes/pandoc/examples/wordpress-odt-handoff.php --self-test`
  - `php lanes/pandoc/examples/wordpress-odt-open-document-handoff.php --self-test`
- Full Pandoc PHP gate:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 59369 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses native PHP DOM parsing,
existing `ZipPackage` in-memory package fixtures, and the direct `OdtReader`
import-report path. No Pandoc binary, Cabal solver/build/test command,
Haskell runner, office suite, zip/unzip command, browser renderer, external
validator, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This direct-reader slice does not repeat accepted `OdfReader` style diagnostics.
It is limited to the registry-exposed `OdtReader` import path and does not add
new style inheritance semantics, content.xml style-use validation, style-map
condition evaluation, table-template application, page-layout rendering, media
extraction, DOCX/EPUB behavior, or full Pandoc ODT reader parity.
