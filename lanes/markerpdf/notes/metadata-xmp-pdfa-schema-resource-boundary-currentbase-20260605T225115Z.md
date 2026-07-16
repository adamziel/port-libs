# markerPDF XMP PDF/A Schema Resource Boundary Current Base

Session: `port-dev-markerpdf-metadata-xmp-20260605T225115Z`

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T225115Z`

Base accepted HEAD: `3dc528622f7842013cb296f245a83118cbe1f25c`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps document metadata separate from visible Markdown extraction. The native no-GPU PHP path maps the searchable-PDF/pdftext/PDFium boundary: catalog `/Metadata` may promote root document XMP, but private or attachment-like RDF resources inside the same packet must not become document-level metadata unless a document-level property references them.

PDF/A extension schema declarations are valid XMP document metadata when they are attached to the document description. This slice keeps that behavior while preventing unreferenced `rdf:about="#..."` schema resources from leaking into `pdfa_extension_schemas`.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPdfaSchemaResourceBoundaryCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL keeps unreferenced private XMP PDF/A schemas out of document metadata
Expected: false
Actual: true

FAIL resolves document-level XMP PDF/A schema resource references without private schema leakage
Expected: 'Document WordPress Import Schema'
Actual: 'Private Attachment Schema'

1 test files, 13 assertions, 2 failures
```

## Implementation

`PdfMetadataExtractor::xmpPdfaExtensionSchemas()` now walks the same document-level XMP description set used for scalar XMP fields. It extracts `<pdfaExtension:schemas>` only from those top-level document nodes, while preserving existing `rdf:resource` and `rdf:nodeID` resolution from a document-level schema property to a same-packet resource node.

This prevents unreferenced private RDF schema resources from becoming document metadata, and still supports document-level resource references for PDF/A extension schemas.

## Verification

Focused behavior:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPdfaSchemaResourceBoundaryCurrentBaseTest.php
```

Passed: `1 test files, 28 assertions, 0 failures`.

Adjacent metadata/XMP family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPdfaSchemaResourceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNameTreeAssociatedSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpExternalAboutBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpResourceReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpResourceWrappedListBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNodeIdBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Passed: `11 test files, 1315 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-xmp-pdfa-schema-resource-boundary-currentbase.php
```

Passed: emitted `schema_count=1`, `schema_names=["Document WordPress Import Schema"]`, `private_schema_promoted=false`, `private_schema_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP behavior tests move `2039 -> 2041`.
- `phpPass` moves `2256 -> 2258`.
- WordPress scenarios move `1943 -> 1944`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream filter decoder, catalog metadata stream boundary, DOM-backed XMP parser, RDF resource-reference handling, PDF/A extension schema review rows, and PdfTextExtractor visible-text isolation. Live OCR, Surya/Texify/Torch model execution, pypdfium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF boundary.

## Non-Overlap

This does not repeat catalog `/Metadata` stream type/subtype validation, XMP packet begin/end slicing, external `rdf:about` scalar filtering, `rdf:resource` scalar/list extraction, resource-wrapped lists, qualified `rdf:value` parsing, name-tree associated schema attachments, encrypted metadata policy, or visible-text exclusion. The bounded new behavior is document-level scoping for XMP PDF/A extension schema declarations.
