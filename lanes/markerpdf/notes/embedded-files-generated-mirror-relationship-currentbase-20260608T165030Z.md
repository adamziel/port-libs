# markerPDF EmbeddedFiles Generated Mirror Relationship Current Base

Session: `port-dev-markerpdf-attachments-20260608T165030Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T165030Z`
Base accepted HEAD: `63e2debc141738e27afa8820a6493fd1cbe7d79e`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable-PDF text through `pdftext.dictionary_output()` and PDF page text APIs in `marker/pdf/extract_text.py`; embedded-file payloads and FileSpec dictionaries are not promoted into visible text.

This native no-GPU PHP boundary keeps FileSpec/EmbeddedFiles metadata as review state for WordPress import while avoiding Python, OCR/model execution, PDF action execution, raster rendering, external PDF tools, and attachment payload text promotion.

## Behavior

`PdfEmbeddedFileExtractor` now merges filename-less catalog `/AF` FileSpec mirrors into an already named `/Names /EmbeddedFiles` row when both FileSpecs point at the same embedded-file stream.

Before this slice, the full embedded-file inventory kept the named row but dropped the generated-name catalog `/AF` mirror before merge, so the row lost:

- `associated_file=true`;
- `associated_file_source=catalog_af`;
- the catalog mirror's `/AFRelationship /Source`;
- associated-file provenance review.

The lightweight `PdfAttachmentExtractor` summary already had this mirror behavior; this patch brings the full embedded-file inventory into the same boundary. Generated mirror provenance also rewrites its payload filename from the fallback `embedded-file` to the target name-tree filename so WordPress review rows do not expose a synthetic attachment name.

## Evidence

Red-first probe before the final fix:

```text
PdfEmbeddedFileExtractor returned one catalog_names_embedded_files row for source.xml,
but it lacked associated_file, associated_file_source, and relationship metadata
even though the catalog /AF mirror had /AFRelationship /Source.
```

Focused regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileGeneratedMirrorRelationshipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS merges generated catalog AF mirrors into named EmbeddedFiles rows with relationship provenance
1 test files, 52 assertions, 0 failures
```

Adjacent attachment coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileGeneratedMirrorRelationshipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1018 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-embeddedfile-generated-mirror-relationship-currentbase.php
```

The smoke emits `attachment_count=1`, `embedded_file_count=1`, `filename=source.xml`, `relationship=Source`, `relationship_role=original_source`, `associated_file_source=catalog_af`, `generated_filename_row_suppressed=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform FileSpec name selection, `/EF` key fallback, `/AFRelationship` operand validation, Mac `/Params`, page `/AF`, annotation `/AF`, StructElem `/AF`, direct FileSpec duplicate-key, related-file, portfolio/PieceInfo, encrypted EFF, xref repair, or lightweight `PdfAttachmentExtractor` relationship mirror coverage.

The new boundary is specifically the full `PdfEmbeddedFileExtractor` dedupe path for a generated-name catalog `/AF` mirror of a named EmbeddedFiles name-tree row.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, FileSpec parsing, embedded-file stream decoding, associated-file provenance review, and WordPress smoke pattern.

Full OCR, Surya/Texify/Torch model execution, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.
