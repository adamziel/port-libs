# Page Associated Files MarkInfo UserProperties Review

Slice: `page-associated-files-markinfo-userproperties-review-currentbase-20260602T1629Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; markerPDF delegates low-level page/PDF extraction to `pdftext` and `pypdfium2`, so this native slice keeps page review metadata separate from visible extracted text and from model execution.
- pypdf constants expose page `/PieceInfo` and `/AF` as page dictionary entries and catalog `/MarkInfo`, `/PieceInfo`, and `/AF` as catalog entries: https://pypdf.readthedocs.io/en/stable/_modules/pypdf/constants.html
- pypdf embedded-file support reads `/AFRelationship`, `/EF`, `/Params /Size`, `/Params /CreationDate`, `/Params /ModDate`, and `/Params /CheckSum`; checksum is the MD5 checksum for uncompressed embedded-file bytes: https://pypdf.readthedocs.io/en/6.1.0/_modules/pypdf/generic/_files.html

## Native Behavior

- `PdfPagePropertyExtractor` now carries embedded-file `/Params /CheckSum` from page-scoped `/AF` FileSpec rows as `checksum`, `checksum_algorithm=md5`, `computed_checksum`, and `checksum_matches`.
- The row remains review-only: it includes filename, relationship, MIME type, size, SHA-256, dates, and checksum state, but never exposes raw associated-file `content`.
- The combined page row composes catalog `/MarkInfo`, page `/PieceInfo`, tagged PDF `/UserProperties`, and page-associated Source/Alternative attachment checksum state.
- `PdfOutlineExtractor` now skips standalone delimiter tokens while parsing object values, preventing attachment stream bytes from causing an endless token append during page-presentation composition.

## Evidence

- Red-first focused gate after adding the combined fixture failed before production changes with a fatal memory error in `PdfOutlineExtractor::tokens()` while `PdfPagePropertyExtractor` composed page-presentation metadata over attachment streams. The same fixture also expected page-associated checksum fields that did not exist on current base.
- Passing focused gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed with `1 test files, 129 assertions, 0 failures`.
- Adjacent page/outline gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed with `2 test files, 406 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-associated-markinfo-userproperties-review-currentbase.php` passed and emitted `page_associated_checksum_matches=[true,false]`, `raw_associated_content_exposed=false`, `excluded_page_associated_payload_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Syntax checks passed for `PdfPagePropertyExtractor.php`, `PdfOutlineExtractor.php`, `PdfPagePropertyExtractorTest.php`, and `wordpress-pdf-page-associated-markinfo-userproperties-review-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests move `558 -> 559`.
- Mapped native PDF semantics move `399 -> 400 / 78`.
- New mapped key: `pdfPageAssociatedMarkInfoUserPropertiesChecksum`.

## Non-Overlap

This does not repeat standalone catalog `/AF` attachment extraction, page `/AF` size/SHA-256 metadata, page `/PieceInfo`, tagged PDF `/UserProperties`, page transition/action metadata, or catalog-associated FileSpec checksum coverage. The new boundary is page-scoped `/AF` embedded-file checksum match state composed with MarkInfo, page PieceInfo, and UserProperties on one page-review row.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page-tree ordering, FileSpec/embedded-file stream decoding, PDF byte-string decoding, visible-text extraction, and tagged-PDF review metadata traversal. Full upstream markerPDF Python/model/pdftext/pypdfium/Surya/Texify benchmark parity remains dependency-gated.
