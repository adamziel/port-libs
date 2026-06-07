# markerPDF xref Prev chain outline generation current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T113602Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260607T113602Z`
Base accepted HEAD: `f716e4283d84f7b543276d2a9be0237167ba1fa0`

## Source Truth

Upstream markerPDF routes searchable-PDF text and document navigation through native PDF parser dependencies before OCR/model fallback. Under the current no-GPU markerPDF scope, xref `/Prev` chain selection must keep current incremental update objects authoritative before WordPress TOC/navigation import, while older `/Prev` rows remain available only for objects not replaced or freed by the current revision.

Some incremental PDFs append generation-one catalog, outline, page, name-tree, action, and content objects, then write a current xref stream whose `/Root` points at `1 1 R` but whose type-one rows have damaged offset fields. The text extractor already recovers this current body by exact generation. The outline extractor also has to recover the reachable current navigation graph before stale generation-zero `/Prev` rows can own the TOC or action review.

## Implementation

`PdfOutlineExtractor::repairOmittedCurrentUpdateGraphRows()` now handles the present-but-damaged current-row case in addition to omitted rows. When a reachable object has an in-use current row with the exact requested generation but the explicit offset does not resolve to that object inside the current update window, it scans the bounded current update body for the newest direct object with the same object number and generation, updates the xref entry, and continues graph traversal through nested references.

The fallback is generation-exact and only runs for `n` rows. Free rows, compressed rows, mismatched generations, stale previous rows, and objects outside the current update window are not promoted.

## Evidence

Red-first scratch before source repair:

```text
PdfOutlineExtractor::getPdfTocWithDestinationViews($pdf) => []
PdfTextExtractor::extractPlainText($pdf) => Current gen one outline page
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs damaged nonzero-generation current outline xref rows before Prev chain rows

1 test files, 17 assertions, 0 failures
```

Adjacent xref/outline gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineXrefPrevChainOmittedRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineXrefStreamPrevChainOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
32 PASS cases
4 test files, 602 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-xref-prev-chain-generation-currentbase.php
```

The smoke exits `0` and reports current generation-one outline selected, current outline action reviewed, current generation page text selected, stale previous outline/action/text excluded, damaged current xref offsets repaired, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfOutlineExtractor.php

php -l lanes/markerpdf/tests/PdfOutlineXrefPrevChainGenerationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfOutlineXrefPrevChainGenerationCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-outline-xref-prev-chain-generation-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-outline-xref-prev-chain-generation-currentbase.php

php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exits `0`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted same-generation omitted outline graph row repair, xref-stream owner selection before post-xref decoys, page-review `/Prev` helper handling, metadata/text damaged-offset repair, free-row suppression, indirect or compressed `/Prev` helper resolution, object-stream carrier repair, classic xref rebuild, CMap/font/filter behavior, or model/OCR/table detection handoffs.

The bounded behavior here is only outline/navigation graph repair for current xref-stream rows that are present, in-use, nonzero-generation, and offset-damaged before stale previous-generation `/Prev` rows are inherited.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref stream decoder, Flate stream decoding, trailer `/Prev` walker, outline/navigation review metadata extraction, name-tree destination resolution, and searchable text extraction paths. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, JavaScript/PDF action execution, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
