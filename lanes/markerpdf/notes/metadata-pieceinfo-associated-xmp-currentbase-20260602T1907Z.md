# metadata-pieceinfo-associated-xmp-currentbase

## Source truth

- Upstream markerPDF boundary: metadata/XMP packets are extraction metadata for review/import decisions, while visible text is produced from page content and supplied pdftext-style text sources.
- PDF parser boundary: catalog `/AF` FileSpec `/Metadata` and `/PieceInfo` application-private `/Private /Metadata` streams are associated-file metadata, not document-root metadata or visible page text.
- Current-base boundary: the latest xref stream controls catalog/FileSpec objects; appended stale duplicate objects after the current `%%EOF` must not replace selected associated-file metadata.

## Implementation

- `PdfMetadataExtractor` now summarizes associated FileSpec `/Metadata` XMP streams with sanitized `xmp_summary` rows containing field names, counts, packet encoding, and UTC-normalized date fields.
- FileSpec `/PieceInfo` application dictionaries are checked for `/Private /Metadata` streams and reported under `provenance_review.piece_info_xmp_metadata`.
- The summaries deliberately omit XMP title, description, creator, producer, author, keyword, raw XML, and embedded-file payload bytes.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php` failed before the source change because `filespec_pieceinfo_metadata_stream` was absent after 8 assertions.
- Focused: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed, `2 test files, 878 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-metadata-pieceinfo-associated-xmp-currentbase.php` passed.
- Syntax and whitespace checks were run for changed PHP files plus `git diff --check -- lanes/markerpdf`.

## Non-overlap

This does not repeat the accepted associated FileSpec OutputIntent/PieceInfo boundary. That slice preserved FileSpec-local Metadata and OutputIntent dictionaries as review-only rows. This slice parses the XMP packets behind associated FileSpec `/Metadata` and FileSpec `/PieceInfo /Private /Metadata` streams into redacted provenance summaries, including field presence/counts and date normalization, while preserving the same payload and visible-text exclusion boundaries.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF dictionary, stream-filter, xref-current-base, XMP packet parsing, date normalization, metadata extraction, and text extraction helpers. The activation gate is the focused markerpdf metadata tests and the WordPress smoke above.
