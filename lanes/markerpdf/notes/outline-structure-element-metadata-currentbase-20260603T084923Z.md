# markerPDF Outline Structure Element Metadata Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260603T084923Z`

Base accepted HEAD: `f0bd4183a2ffe1c741d3688a1bfed43e7facac09`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` treats bookmarks as TOC/navigation metadata, while `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks separate from TOC metadata.
- PDF outline item dictionaries can associate bookmarks with tagged-PDF structure elements through `/SE`. This is navigation/accessibility review metadata and must not become visible WordPress paragraph text.

## Implementation

- `PdfMetadataExtractor::document_outline` rows now resolve outline item `/SE` values through the existing native StructElem review collector.
- Per-outline rows preserve review-only structure metadata: object number, raw/mapped role, page/page object, MCIDs, language/title/Alt/ID/class fields, and associated FileSpec provenance hashes.
- The document outline summary records structure-element count, objects, roles, raw roles, MCIDs, and associated-file count when outline `/SE` links are present.
- Associated FileSpec payload bytes remain omitted from metadata and visible text; only payload size/checksum/hash provenance is surfaced.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementMetadataCurrentBaseTest.php
```

Result: `1 test files, 59 assertions, 0 failures`.

Adjacent outline/metadata gate:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureElementMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineNameTreeActionStructureCurrentBaseTest.php
```

Result: `4 test files, 1054 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-structure-element-metadata-currentbase.php
```

Result: emitted non-empty block output with `structure_role=H1`, `structure_mcids=[0]`, `associated_filename=outline-source.xml`, `payload_content_omitted=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineStructureElementMetadataCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-structure-element-metadata-currentbase.php
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted document outline summary, named-destination outline resolution, page-label/transition/action context, target-page tagged-content enrichment, catalog StructTreeRoot review, or structure-tree associated-file metadata in isolation. The bounded behavior is outline item `/SE` association inside `PdfMetadataExtractor::document_outline`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, dictionary/value reader, page-tree indexer, StructElem review collector, and associated FileSpec provenance helpers. GPU/model/OCR execution, Surya/Texify/Torch, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser/review behavior. For outline/metadata specifically, avoid repeating `document_outline` or outline `/SE`; choose only a remaining catalog/outline edge that adds focused parser behavior and WordPress-visible review value.
