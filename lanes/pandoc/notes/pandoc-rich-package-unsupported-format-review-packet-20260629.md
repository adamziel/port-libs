# Pandoc Rich Package Unsupported-Format Review Packet Slice 2026-06-29

## Summary

Mapped one bounded direct-format registry accounting case for Pandoc rich
package unsupported-format review surfaces.

- `PandocFormatRegistry::richPackageUnsupportedFormatSummary()` now separates
  direct native package inputs, supported package outputs, unsupported package
  outputs, partial-input/unsupported-output package tokens, output-only
  unsupported tokens, source-alias gaps, and extension-level unsupported
  directions.
- `PandocFormatRegistry::richPackageFormatReviewPacket()` combines that
  summary with the existing `RichPackageUnsupportedFormatRegistry` report and
  direct PHP input/output support maps so review handoffs do not need to
  re-derive rich package gaps from per-format rows.
- DOCX, ODT, EPUB, IPYNB, PPTX, and XLSX remain bounded native input support
  only; EPUB/EPUB3 remain the only bounded native package outputs.
- DOCX, ODT, OpenDocument, EPUB2, IPYNB, PPTX, chunked HTML, ICML, and PDF
  writer/package output parity remains explicitly unsupported.

No Pandoc executable, office suite, notebook tooling, TeX/PDF engine, browser
renderer, zip/unzip command, Cabal solver/build/test command, Haskell runner,
external validator, online service, live provider test, or live-service
provider test was used.

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - Result: `1 test files, 279 assertions, 0 failures`.

## Metric Delta

- `lane-status.json` `phpPass`: `457 -> 458`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2304 -> 2305`
- `mappedPandocRichPackageUnsupportedFormatCases`: `1`
- `pandocRichPackageUnsupportedFormatAssertions`: `41`
