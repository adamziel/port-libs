# markerPDF Embedded Files Indirect AF Array Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T091157Z`

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T091157Z`

Base accepted HEAD: `e6968ed818a69e9dc12dd229c89caaf4bc025eb5`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF and attachment review before OCR/model fallback. This slice stays in native no-GPU PDF parser and FileSpec preflight behavior.
- PDF associated-file arrays (`/AF`) are object values attached to catalog, page, or annotation dictionaries. When an indirect object is used as the array value, the resolved object must be the array value, not an array prefix followed by extra top-level operands.
- WordPress attachment import should keep safe EmbeddedFiles name-tree attachments while failing closed on malformed catalog/page/annotation associated-file arrays and never exposing embedded payload bytes as visible text.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now require catalog, page, and annotation `/AF` values that resolve through an indirect object to contain exactly one top-level array. If the resolved object starts with a valid array but has a trailing operand such as `20 0 R`, the whole associated-file array is ignored.

The valid `/Names /EmbeddedFiles` name-tree path remains accepted, so safe discoverable attachments still appear in both the lightweight WordPress summary and the full embedded-file inventory. Malformed catalog `/AF`, page `/AF`, and annotation `/AF` decoys are excluded from filenames, checksums, payload summaries, and visible text.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectAssociatedFileArrayBoundaryCurrentBaseTest.php
```

Result: failed with expected attachment count `1` and actual attachment count `4`.

## Focused Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectAssociatedFileArrayBoundaryCurrentBaseTest.php
```

Result: `1 test files, 79 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile).*Test\.php$' | sort)
```

Result: `61 test files, 4520 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-af-array-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `embedded_file_count=1`, `malformed_catalog_af_array_rejected=true`, `malformed_page_af_array_rejected=true`, `malformed_annotation_af_array_rejected=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentIndirectAssociatedFileArrayBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-af-array-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: all PHP files reported no syntax errors, `lane-status json ok`, and `git diff --check -- lanes/markerpdf` produced no output.

## Non-Overlap

This does not repeat accepted catalog/page `/AF` extraction, direct `/AF` array duplicate/trailing operand checks, direct FileSpec duplicate-key rejection, annotation FileAttachment extraction, name-tree `/Names`/`/Kids` traversal, EmbeddedFiles mirror dedupe, related-file `/RF` review, encrypted `/EFF` redaction, xref/object-stream attachment selection, Portfolio/PieceInfo/XMP/OutputIntent metadata, attachment checksum review, or stream-filter behavior. The bounded behavior is only rejecting malformed indirect catalog/page/annotation associated-file array objects that contain trailing top-level operands.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, dictionary raw-value scanner, associated-file walkers, FileSpec/EmbeddedFile stream resolution, checksum review, and existing WordPress smoke path. GPU/model execution, live OCR, PDFium rendering, Surya/Texify/Torch model paths, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
