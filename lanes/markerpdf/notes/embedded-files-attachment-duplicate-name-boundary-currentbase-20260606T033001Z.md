# EmbeddedFiles Attachment Duplicate Name Boundary Current Base

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF parsing through parser-backed PDF text and document loading before OCR/model fallback. In this native no-GPU PHP lane, the equivalent import boundary is catalog `/Names /EmbeddedFiles` attachment review without executing OCR, models, Python workers, PDFium, or external tools.

PDF name-tree keys are document identifiers and duplicate keys are malformed. The lightweight `PdfAttachmentExtractor` already kept the first successful duplicate `/EmbeddedFiles` name-tree key. The full `PdfEmbeddedFileExtractor` still returned later stale duplicate FileSpecs, so a WordPress import path using full embedded-file review could see two attachments while the preflight summary saw one.

## Implementation

`PdfEmbeddedFileExtractor::dedupeEmbeddedFiles()` now tracks successful `catalog_names_embedded_files` name-tree names and skips later rows for the same name key. Catalog/page `/AF` mirrors are still merged by object/stream identity and are not suppressed by this name-tree-only boundary.

The focused fixture builds a PDF 2.0 catalog `/EmbeddedFiles` name tree with two child nodes under the same effective `/Limits`, both using key `shared.csv`. The first FileSpec points at `current-shared.csv`; the second points at `stale-shared.csv`. After the patch:

- full embedded-file extraction returns only `current-shared.csv`;
- lightweight attachment summary also returns only `current-shared.csv`;
- the stale filename, stale payload, and stale checksum stay out of full review JSON and summary JSON;
- WordPress-visible text remains the page content only.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentDuplicateNameBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first duplicate EmbeddedFiles name-tree key across full and summary attachment review
Values are not identical
Expected: 1
Actual: 2

1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentDuplicateNameBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first duplicate EmbeddedFiles name-tree key across full and summary attachment review

1 test files, 49 assertions, 0 failures
```

Adjacent attachment/embedded-file family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Attachment|EmbeddedFile).*Test\.php' | sort)
Focused test run: 36 selected test files (root lock skipped)
...
36 test files, 2757 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embedded-files-duplicate-name-boundary-currentbase.php
```

Emits `embedded_file_count=1`, `attachment_count=1`, `stale_duplicate_excluded_from_full_extractor=true`, `stale_duplicate_excluded_from_summary=true`, `payload_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted attachment work for duplicate FileSpec dictionary keys, escaped duplicate keys, indirect name keys, indirect Names/Kids arrays, xref generation repair, object streams, filter stacks, DecodeParms, encrypted EFF redaction, page/catalog associated files, FileAttachment annotations, Portfolio metadata, PieceInfo, related files, path review, or EOF/xref stale-object boundaries. The bounded behavior here is specifically duplicate catalog `/EmbeddedFiles` name-tree keys leaking stale later FileSpecs through the full embedded-file extractor while the lightweight summary path already kept the first key.

## Dependency closure

No new support component is needed. This reuses the native PHP object scanner, name-tree traversal, EmbeddedFile stream decoding, attachment summary, text extractor, WordPress smoke path, and focused PHP runner. Live OCR, Surya/Torch, Texify, PDFium/pypdfium2, Streamlit/FastAPI workers, online services, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
