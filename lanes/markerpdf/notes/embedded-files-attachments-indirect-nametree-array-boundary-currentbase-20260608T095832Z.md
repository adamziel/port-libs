# markerPDF EmbeddedFiles Indirect NameTree Array Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T095832Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T095832Z`

Base accepted HEAD: `0562d818cd891a6f7e8ab8d95873ec1e5b686ccc`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable-PDF page text on parser/PDFium-backed text paths before OCR/model fallback. Embedded FileSpec payloads are review metadata for import, not visible WordPress paragraph text.

PDF EmbeddedFiles name-tree `/Names` and `/Kids` operands may be indirect array objects. If the resolved object contains a valid array prefix followed by another top-level operand, the native parser must fail closed instead of pairing the prefix and importing stale or decoy FileSpecs.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now require EmbeddedFiles name-tree `/Names` and `/Kids` operands to resolve to exactly one top-level array object before traversing FileSpec rows.

- A malformed indirect `/Names 50 0 R` where object `50` is `[(bad.xml) 20 0 R] 30 0 R` is skipped, while a valid sibling leaf remains available.
- A malformed indirect `/Kids 50 0 R` where object `50` is `[7 0 R] 30 0 R` suppresses that name-tree branch, while a catalog `/AF` fallback attachment remains available.
- Attachment summaries still omit payload bytes, and `PdfTextExtractor` keeps embedded payload text out of visible WordPress paragraphs.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeArrayOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects indirect EmbeddedFiles Names array objects with trailing operands before WordPress attachment review
Values are not identical
Expected: 1
Actual: 2
FAIL rejects indirect EmbeddedFiles Kids array objects with trailing operands before WordPress attachment review
Values are not identical
Expected: 1
Actual: 2

1 test files, 2 assertions, 2 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameTreeArrayOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects indirect EmbeddedFiles Names array objects with trailing operands before WordPress attachment review
PASS rejects indirect EmbeddedFiles Kids array objects with trailing operands before WordPress attachment review

1 test files, 120 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile).*Test\.php$' | sort)
Focused test run: 62 selected test files (root lock skipped)
62 test files, 4640 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-nametree-array-boundary-currentbase.php
```

Result: exits 0 and emits `indirect_names_array_rejected=true`, `indirect_kids_array_rejected=true`, `valid_sibling_preserved=true`, `catalog_af_fallback_preserved=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted indirect `/Names` array resolution, indirect `/Kids` array resolution, direct node `/Names` trailing operand rejection, name-tree `/Names`/`/Kids` mixed-node handling, leaf ordering, kid limit ordering, duplicate node keys, direct FileSpec duplicate-key rejection, catalog/page/annotation `/AF` exact-array rejection, stream-filter behavior, encrypted `/EFF` redaction, portfolio/PieceInfo/XMP/OutputIntent metadata, object-stream/xref selection, CMap/font work, or model/OCR paths. The bounded behavior is only rejecting indirect EmbeddedFiles name-tree array objects that contain trailing top-level operands after the array.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, exact top-level array boundary checks, name-tree traversal, FileSpec parser, embedded-file stream decoding, checksum review, text payload exclusion, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium raster rendering, decryption, media playback, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
