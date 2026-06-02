# MarkInfo PieceInfo Page Associated Review Boundaries

Slice: `markinfo-pieceinfo-page-associated-review-boundaries-currentbase-20260602T084838Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF object/page parsing to `pdftext`/`pypdfium2` before conversion metadata is carried through `marker/convert.py`. This native slice keeps page-level PDF review metadata separate from visible extracted text and from model execution.
- pypdf constants document page `/PieceInfo` and page `/AF` as optional page dictionary entries, catalog `/MarkInfo` and catalog `/AF` as optional catalog entries, and PDF 2.0 `/AFRelationship` names including `/Source` and `/Alternative`: https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html
- PDFBox exposes MarkInfo as structured-document review flags through `PDMarkInfo::isMarked()`, `usesUserProperties()`, and `isSuspect()`: https://pdfbox.apache.org/docs/2.0.4/javadocs/org/apache/pdfbox/pdmodel/documentinterchange/logicalstructure/PDMarkInfo.html

Native PHP behavior added:

- `PdfPagePropertyExtractor` now reports catalog `/MarkInfo` review flags on page-review rows as `mark_info.source= catalog_mark_info`, `marked`, `user_properties`, and `suspects`.
- The same extractor now walks page-scoped `/AF` arrays and emits review-only `page_associated_files` rows for direct and indirect Filespec dictionaries, including filename, description, `/AFRelationship`, MIME subtype, embedded-file object, decoded size, declared size, and SHA-256. Raw associated-file payload bytes are not exposed in the page-review row.
- Existing page `/PieceInfo` and tagged PDF `/UserProperties` behavior stays intact, including the previous `mark_info_user_properties` compatibility field when structure user properties are present.
- `wordpress-pdf-page-pieceinfo-userproperties-import.php` now demonstrates the combined WordPress review path: visible page text remains a Gutenberg paragraph, while MarkInfo flags, page PieceInfo, UserProperties, and page-associated Source/Alternative files are emitted as review comments.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` failed with `Actual: NULL` for the expected `mark_info` row on `extracts MarkInfo flags and page associated Filespec review boundaries`.
- Passing focused gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed with `1 test files, 73 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-page-pieceinfo-userproperties-import.php` emitted `page_review_count=1`, `mark_info.suspects=true`, `page_associated_relationships=["Source","Alternative"]`, `excluded_page_associated_payload_text=true`, and the visible `Page Property Review` paragraph.
- Syntax checks passed for `PdfPagePropertyExtractor.php`, `PdfPagePropertyExtractorTest.php`, and `wordpress-pdf-page-pieceinfo-userproperties-import.php`.
- `git diff --check -- lanes/markerpdf` passed.

Dependency closure:

- No new support component is needed. This reuses native PDF object scanning, page-tree ordering, dictionary/value parsing, PDF string/name decoding, embedded-file stream decoding, and fallback-text EF payload exclusion. Full upstream Python/model/benchmark parity remains gated on `pdftext`, `pypdfium2`, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

Non-overlap:

- This does not repeat accepted catalog `/AF` associated-file extraction, Filespec `/PieceInfo /Private` stream review boundaries, page `/PieceInfo` plus UserProperties extraction, catalog `/Collection`, EmbeddedFiles name-tree extraction, or fallback EF payload exclusion.
- The new boundary is specifically the page-level combination of catalog `/MarkInfo` review flags with page `/AF` Filespec metadata alongside page `/PieceInfo` review metadata.
