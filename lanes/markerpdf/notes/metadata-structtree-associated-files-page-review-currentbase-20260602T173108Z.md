# markerPDF Metadata StructTree Associated Files Page Review

Session: `port-dev-markerpdf-meta34pdf-20260602T173108Z`

Accepted base: `f6a226052136abadc56f7b8d8b89c4b84d502d1b`

Implemented behavior:

- `PdfPagePropertyExtractor` now preserves `associated_file_count` and sanitized `associated_files` from the owning StructElem when emitting page-level `structure_marked_content` MCID review rows.
- Catalog `/AF`, page `/AF`, and StructElem `/AF` rows remain separate review surfaces, so WordPress import metadata can attribute attachments to the document, page, or tagged region without merging contexts.
- StructElem-associated payload bytes, nested XMP packet bytes, and attachment-local OutputIntent ICC bytes remain review-only hash/provenance metadata and are not exposed as visible paragraph text.

Source-truth evidence:

- Upstream `sddai/markerPDF` at the pinned lane manifest commit routes PDF extraction through pdftext/PDFium-style page/object boundaries before WordPress-visible conversion. This native slice keeps PDF object metadata review-only and page-aligned without running Python, pdftext, pypdfium, Surya, Texify, or raster/model tooling.
- PDF associated files can be attached to logical structure elements as contextual FileSpec relationships. The already accepted `metadata-structure-outputintent-filespec-review-currentbase-20260602T1721Z` slice parsed those StructElem `/AF` rows in document metadata; this rebase propagates that same sanitized metadata to the page MCID review row that WordPress import code inspects.

Verification evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` failed on the new test with missing `associated_file_count` (`Expected: 2`, `Actual: NULL`).
- After fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php` passed: `1 test files, 204 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-structtree-associated-files-page-review-currentbase.php` passed and emitted `catalog_associated_file_count=1`, `page_associated_file_count=1`, `structure_associated_file_count=2`, `relationship_roles=[original_source,supplemental_representation]`, `payload_content_omitted=true`, and `visible_text_excludes_payloads=true`.

Status delta:

- `phpPass`: `604 -> 605`.
- `wordpressScenarios`: `604 -> 605`.
- mapped markerPDF semantics: `438 -> 439 / 78`.

Non-overlap:

- This does not repeat the accepted metadata-only StructElem `/AF` FileSpec provenance slice, catalog `/AF` extraction, page `/AF` extraction, page PieceInfo/UserProperties extraction, page article-thread/StructTree MCR composition, OutputIntent root metadata, or embedded-file payload exclusion.
- The bounded behavior is only the conflict/rebase seam where StructElem-associated files already parsed by `PdfMetadataExtractor` are carried onto page-level MCID review rows in `PdfPagePropertyExtractor`.

Dependency closure:

- No new support component is needed. The slice reuses the native PDF object scanner, StructTree metadata parser, FileSpec/embedded-file metadata review helper, stream filter decoder, checksum/provenance metadata, page review composition, and WordPress example renderer.

Next task:

- Continue with non-overlapping markerPDF current-base parser, metadata, outline, image, form, security, or font handoffs that add focused PHP behavior evidence.
