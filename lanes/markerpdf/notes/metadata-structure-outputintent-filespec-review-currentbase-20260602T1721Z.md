# markerPDF metadata structure OutputIntent FileSpec review current-base slice

Session: `port-dev-markerpdf-meta32pdf-20260602T1721Z`

Accepted base: `49180e79432b8b918699ff28f84476d5fe362bc7`

Implemented behavior:

- Added review-only `/AF` FileSpec handling on tagged PDF structure elements in `PdfMetadataExtractor`.
- Each structure-associated file records relationship, filename, MIME type, decoded payload size/hash/checksum metadata, decoded XMP stream hash metadata, and nested attachment-local PDF/A OutputIntent summaries.
- Embedded payload bytes, nested XMP titles, MathML/XML payloads, and associated ICC profile bytes remain omitted from document metadata roots and visible WordPress paragraph text.
- Existing OutputIntent-associated FileSpec parsing now reuses the same bounded review helper, preserving accepted output shape with the original `output_intent_associated_files` source label.

Source-truth evidence:

- markerPDF/marker output paths expose a document metadata dictionary and JSON block-tree output; this native slice keeps the metadata-review boundary in PHP without running marker's Python model stack.
- PDF Association Associated Files guidance describes `/AF` FileSpec associations on PDF objects with semantic `/AFRelationship` roles and recommends contextual review of associated objects and relationships: https://pdfa.org/files-inside-pdf/
- Library of Congress PDF/A-3 notes that associated files can be attached to the containing document or logical structure, with predefined `/AFRelationship` values including `Source`, `Data`, `Alternative`, `Supplement`, and `Unspecified`: https://www.loc.gov/preservation/digital/formats/fdd/fdd000360.shtml
- This builds on the already accepted lane slices for catalog OutputIntent metadata, OutputIntent-associated FileSpec provenance, catalog Collection/Filespec review, and StructTreeRoot role/language review, without duplicating image ColorKey, page associated-file, or existing OutputIntent root behavior.

Verification evidence:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-structure-outputintent-filespec-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed: `1 test files, 525 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-structure-outputintent-filespec-review-currentbase.php` passed and emitted `associated_file_count=2`, `relationship_roles=[original_source,supplemental_representation]`, `root_pdfa_identifiers=[Root Structure sRGB]`, `nested_xmp_payload_omitted=true`, and `associated_pdfa_not_promoted_to_root=true`.

Status delta:

- `phpPass`: `583 -> 584`.
- `wordpressScenarios`: `583 -> 584`.
- mapped markerPDF semantics: `420 -> 421 / 78`.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PDF dictionary, stream filter, embedded-file checksum, XMP stream hash, and OutputIntent profile review helpers.

Next task:

- Continue with non-overlapping markerPDF review boundaries such as unresolved security/outline/form current-base rework or remaining parser/metadata edge cases; do not repeat structure-element `/AF` FileSpec provenance.
