# markerPDF hybrid linearized object-stream generation repair

Micro-slice: `xref-hybrid-linearized-object-stream-generation-currentbase`
Session: `port-dev-markerpdf-xref64-20260602T213951Z`
Base accepted HEAD: `c3a3b3436899d5af64fa2dad7e137908759c83df`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native page text through `marker/pdf/extract_text.py::get_text_blocks()` using pdftext dictionary output and `naive_get_text()` using pypdfium/PDFium page text. That makes current PDF object ownership, page-tree recovery, object-stream expansion, and linearized hint-table exclusion parser dependency boundaries for this PHP port. PDFium/pypdfium object-stream parsing treats object-stream members as compressed generation-zero objects, so current direct nonzero generation references must not bind to stale compressed generation-zero members.

PDF object streams carry generation-zero members. Linearized `/H` hint-table byte ranges are not page content, but those byte ranges should exclude only the compressed member body they cover. If a current hybrid xref graph later repairs a direct nonzero generation for the same object number, that direct generation remains the current page object.

## Behavior

`PdfTextExtractor` now removes linearized hint-table object-stream members with generation/body provenance instead of object number alone. A hinted compressed generation-zero member is still excluded before WordPress paragraph extraction, but a repaired direct generation for the same object number is preserved when the current hybrid page tree explicitly references it.

The focused PDF fixture covers:

- first-object `/Linearized` dictionary with `/H` pointing inside an object-stream member body;
- current xref table plus companion `/XRefStm`;
- compressed generation-zero `/Pages` object that references `4 1 R`;
- stale compressed generation-zero page object `4 0` inside the hinted byte range;
- current direct page object `4 1` whose content remains authoritative;
- current catalog/root at generation zero, so there is no final nonzero-root repair pass masking the bug.

## Red-first evidence

Before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves repaired direct generation page when a linearized hint range covers the stale compressed member
Expected: Current linearized hybrid page / Hinted compressed generation skipped
Actual: empty text line list
1 test files, 1 assertions, 1 failures
```

After the repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves repaired direct generation page when a linearized hint range covers the stale compressed member
1 test files, 9 assertions, 0 failures
```

## WordPress smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-linearized-object-stream-generation-currentbase.php
uses_current_linearized_hybrid_page=true
preserves_repaired_direct_generation_page=true
excludes_hinted_stale_generation_zero_page=true
excludes_hinted_stale_generation_zero_metadata=true
```

The smoke renders only:

- `Current linearized hybrid page`
- `Hinted compressed generation skipped`

## Non-overlap

This does not repeat accepted standalone linearized hint-table exclusion, `/Prev` hint-range startxref precedence, hybrid direct-generation repair, hybrid object-stream generation repair, current hybrid table direct-row precedence, object-stream offset-owner review, xref-stream zero-width member-index repair, or previous-carrier generation guards. The new bounded behavior is specifically the composition of linearized hint byte ranges with hybrid xref object-stream expansion and direct-generation repair when the current root is generation zero.

## Dependency closure

No new support component is needed. This reuses the native PHP direct-object scanner, startxref/xref table/xref-stream parser, object-stream decoder, linearized hint-range detector, direct-generation repair, page-tree walker, content stream decoder, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Final verification

Focused adjacent xref/linearized gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefLinearizedPrevHintStartxrefCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridGenerationRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 78 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefHybridLinearizedObjectStreamGenerationCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-linearized-object-stream-generation-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-linearized-object-stream-generation-currentbase.php
```

Status/manifest JSON validation:

```text
lanes/markerpdf/lane-status.json: valid
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json: valid
```

Diff check:

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.
