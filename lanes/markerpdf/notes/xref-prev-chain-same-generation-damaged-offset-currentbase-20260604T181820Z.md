# markerPDF xref Prev chain same-generation damaged-offset repair

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260604T181820Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing. Under the current no-GPU markerPDF scope, the PHP lane owns native xref traversal before WordPress text, metadata, and attachment review.

PDF incremental updates can append replacement generation-zero objects after a previous xref section, then publish a latest xref stream with `/Prev`. When those latest in-use rows have damaged explicit offsets, the current update bodies between `/Prev` and the latest xref stream are still the current-base objects; stale previous-section text, Info/XMP, and EmbeddedFiles rows must not win.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now repair xref-stream type-1 rows with damaged explicit offsets when a matching same-generation direct object body was appended after the `/Prev` xref section and before the current xref stream.

The focused fixture keeps stale generation-zero catalog/page/content/XMP/Info/EmbeddedFiles rows in the previous xref table, appends current generation-zero replacements, then emits a latest xref stream whose rows for those objects all point to offset `0`. WordPress import now selects the current page text, `Current Same Generation XMP Title`, current Info/catalog language, and `current-same-generation.xml` attachment while excluding stale previous-section content.

## Evidence

Red baseline after adding the focused case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs same-generation current update objects when xref-stream Prev rows have damaged explicit offsets

1 test files, 55 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
5 PASS cases

1 test files, 71 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*Test.php lanes/markerpdf/tests/PdfParserXref*Test.php lanes/markerpdf/tests/PdfObjectStream*Test.php lanes/markerpdf/tests/PdfParserObjectStream*Test.php
58 test files, 967 assertions, 0 failures
```

Metadata/embedded-file gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php lanes/markerpdf/tests/PdfEmbedded*Test.php
17 test files, 1856 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-same-generation-damaged-offset-currentbase.php
same_generation_text_selected=true
same_generation_metadata_selected=true
same_generation_attachment_selected=true
stale_prev_same_generation_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted nonzero trailer `/Root` and `/Info` generation repair, xref-stream malformed `/Index` rows whose offsets already point to current objects, hybrid free/direct precedence, object-stream carrier generation repair, object-stream `/Prev` free-carrier repair, current trailer `/Encrypt` inheritance, or stream-filter operand owner boundaries.

The bounded behavior here is specifically same-generation current direct object bodies appended inside an incremental update whose latest xref-stream `/Prev` rows have damaged explicit offsets.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream decoder, `/Prev` chain merger, Flate stream decoder, text extractor, metadata extractor, embedded-file extractor, and WordPress smoke renderer. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
