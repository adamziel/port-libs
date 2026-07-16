# metadata-xmp-nametree-associated-schema-currentbase

Base: `bb708b034859e01609243fd0084dfe679ed88069`

Slice: parse root XMP PDF/A extension schema declarations and correlate them
with current xref-selected Catalog `/Names` `/EmbeddedFiles` FileSpec rows whose
`/AFRelationship` is `/Schema`.

Source truth:

- Upstream `sddai/markerPDF` is a Python PDF-to-structured-content pipeline
  using `pdftext`/PDF parser behavior before Markdown/WordPress output.
- The lane manifest already maps XMP metadata, PDF/A OutputIntent metadata,
  EmbeddedFiles name-tree attachment metadata, FileSpec `/AFRelationship`,
  checksum review, and current-xref metadata precedence as native parser
  boundaries.
- PDF parser behavior for this slice is: root XMP schema declarations are
  document metadata; `/Names` `/EmbeddedFiles` FileSpecs are attachment review
  metadata; schema XML payloads, FileSpec-local XMP text, and ICC bytes are not
  promoted to document roots or visible text.

Implementation:

- `PdfMetadataExtractor` now parses `pdfaExtension:schemas` root XMP rows into
  `pdfa_extension_schemas`.
- When a root PDF/A OutputIntent is present, it composes those XMP schema rows
  with `pdfa_associated_name_tree` / `pdfa_associated_files` entries whose
  relationship role is `schema_definition`, producing
  `pdfa_xmp_associated_schema`.
- The associated schema-file rows keep only review-safe metadata: names,
  relationship role, hashes/checksums, attachment-local XMP summaries, and
  attachment-local OutputIntent summaries.

Focused evidence:

- Red-first before implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNameTreeAssociatedSchemaCurrentBaseTest.php`
  failed at missing XMP extension schema count.
- After implementation:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNameTreeAssociatedSchemaCurrentBaseTest.php`
  passed `1 test files, 49 assertions, 0 failures`.
- Metadata family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php`
  passed `15 test files, 1440 assertions, 0 failures`.
- Example smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-nametree-associated-schema-currentbase.php`
  passed.

Status delta:

- MarkerPDF behavior tests: `915 -> 916` pass / `0` fail.
- Mapped semantics: `644 -> 645 / 78`.
- WordPress scenario count: `915 -> 916`.

Non-overlap:

- This does not repeat accepted `pdfMetadataPdfaAssociatedNameTreeCurrentBase`
  or `pdfMetadataXmpOutputIntentNameTreeCurrentBase`; those summarize existing
  name-tree files and generic name-tree value dictionaries.
- This adds the missing root XMP PDF/A extension-schema parser and the
  cross-surface schema FileSpec correlation summary.

Dependency closure:

- No new support component is needed. The slice reuses native PHP PDF object,
  XMP DOM parsing, stream filter, OutputIntent, name-tree, and FileSpec
  provenance helpers.
- No Python models, `pdftext`, `pypdfium2`, Streamlit, FastAPI, or external PDF
  tools were executed.
