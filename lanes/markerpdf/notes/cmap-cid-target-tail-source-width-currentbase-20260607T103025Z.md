# CMap CID Target Tail Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260607T103025Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T103025Z`
Accepted base: `f621e81917015d64a089d0c0844fa389408ad093`

## Source Truth

`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This slice stays in the current no-GPU markerPDF scope and uses the native searchable-PDF parser path only.

The behavior boundary is PDF CMap parsing for Type0 font `/Encoding` maps: source character codes map to descendant CIDs, and descendant CIDFont `/W` widths are keyed by those CIDs. A malformed CID target token must not be accepted as an integer-prefix match that overrides an earlier valid source-to-CID mapping, because that can corrupt source-width word-gap fallback.

## Behavior

`PdfTextExtractor::parseCidChars()` and `PdfTextExtractor::parseCidRanges()` now require integer CID target operands to end at a PDF token boundary before accepting the row. This rejects rows such as `40.5` and `41tail` instead of treating them as CID `40` or `41`, while preserving valid integer rows and comments.

The focused fixture uses a valid first range mapping source codes `<10>` through `<13>` to CIDs `60..63`, then malformed later `begincidchar` or `begincidrange` rows that used to override the valid range. The valid CID widths produce `Wide Thin`; the malformed override path collapsed that to `WideThin`.

## Evidence

Red-first before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapCidTargetTailSourceWidthCurrentBaseTest.php
Result: 1 test files, 2 assertions, 2 failures
Failure: expected ["Wide Thin"], actual ["WideThin"]
```

Focused after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapCidTargetTailSourceWidthCurrentBaseTest.php
Result: 1 test files, 22 assertions, 0 failures
```

Adjacent CMap/CID width family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
Result: 28 test files, 698 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapCidTargetTailSourceWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-cid-target-tail-source-width-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-cmap-cid-target-tail-source-width-currentbase.php
```

The WordPress smoke exits 0 and reports `visible_text_preserved=true`, `text_runs_preserved=true`, `malformed_cidchar_targets_rejected=true`, `malformed_cidrange_target_rejected=true`, `false_join_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2836 -> 2838`
- `wordpressScenarios`: `2380 -> 2381`
- Mapped upstream denominator: unchanged

## Non-Overlap

This slice does not cover plus or negative declared counts, array decoys, large/lazy ranges, sparse codespaces, late `usecmap`, notdef rows/chars/ranges, bytewise codespace, ToUnicode row tails, Type3 fonts, xref repair, stream filters, annotations, forms, metadata, images, OCR/model execution, or supplied table/equation handoffs.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object scanner, stream decoder, CMap parser, CIDFont width lookup, styled text bbox builder, and WordPress smoke path. GPU/model/OCR execution, PDFium, and external PDF tools remain out of scope under the current markerPDF lane rules.
