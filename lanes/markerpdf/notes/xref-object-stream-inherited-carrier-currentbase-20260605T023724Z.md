# markerPDF xref object-stream inherited carrier current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T023724Z`
Session: `port-dev-markerpdf-object-xref-20260605T023724Z`
Base accepted HEAD: `76df382e122b77d31da81d50dde5ba40cf010573`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable PDF page text through `marker/pdf/extract_text.py` and `pdftext`/PDFium parser behavior before model execution. This native PHP slice stays in the no-GPU parser scope.

PDFium object-stream parsing selects objects through xref type-2 rows and the selected `/ObjStm` carrier member index. The relevant native contract is preserving the current xref chain's carrier ownership while exposing whether a carrier row was inherited through `/Prev`.

## Behavior

The focused fixture builds an incremental PDF where:

- the previous xref stream selects direct object-stream carrier `6 0`;
- the latest xref stream selects page object `4` as a type-2 member of carrier `6`, but omits a direct carrier row;
- the latest revision updates the catalog/page tree and page content, while stale direct page object `8` would leak old text if fallback scanning won.

Text extraction already selected the current compressed page. The missing behavior was review provenance: `extractXrefObjectStreamIndexReview()` reported the carrier as xref-selected but did not expose that the carrier xref row was inherited from `/Prev`.

`PdfTextExtractor::xrefEntriesFromOffsetChain()` now annotates xref entries merged from previous sections with `inheritedFromPrev` and `inheritedXrefOffset`. `extractXrefObjectStreamIndexReview()` surfaces that on compressed-object review rows as `object_stream_entry_inherited_from_prev` and `object_stream_inherited_xref_offset`.

After the patch, WordPress paragraph extraction emits only:

- `Current inherited carrier page`
- `Previous carrier row reused`

The stale direct fallback page, compressed member dictionary note text, Python workers, pdftext, pypdfium, model execution, raster execution, and external PDF tools remain excluded.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reuses inherited direct object-stream carrier rows for current xref type-2 members (lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php)
Values are not identical
Expected: true
Actual: NULL

1 test files, 16 assertions, 1 failures
```

Focused passing run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reuses inherited direct object-stream carrier rows for current xref type-2 members

1 test files, 21 assertions, 0 failures
```

Adjacent xref/object-stream run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamInheritedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamFreeOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 309 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-inherited-carrier-currentbase.php
```

The smoke emitted `uses_current_inherited_carrier_page=true`, `reuses_previous_carrier_row=true`, `excludes_stale_direct_fallback=true`, `excludes_compressed_member_metadata=true`, `compressed_entry_count=1`, `object_stream=6`, `object_stream_entry_inherited_from_prev=true`, `object_stream_inherited_xref_offset=650`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, plus Gutenberg paragraphs for only the current text.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream carrier free-entry repair, generation replacement rebuild, zero-width member-index recovery, duplicate member rejection, xref-stream indirect `/Prev` helper resolution, object-stream filter-chain operand recovery, hybrid owner precedence, free-entry suppression, nonzero generation reference preservation, or startxref stale rebuild boundaries.

The bounded behavior here is specifically provenance for a valid direct `/ObjStm` carrier xref row inherited from `/Prev` while the latest xref stream owns the current type-2 member row.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct object scanner, xref-stream decoder, `/Prev` chain merger, object-stream expander, page-tree walker, content-stream decoder, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains intentionally outside this no-GPU slice for live OCR, Surya/Torch, Texify, Streamlit/FastAPI model workers, and exact model benchmark parity.
