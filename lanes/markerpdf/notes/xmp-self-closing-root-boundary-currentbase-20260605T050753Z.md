# XMP Self-Closing Root Boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T050753Z`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives searchable PDF text and document metadata from PDF parser/PDFium-style document boundaries, not by promoting arbitrary XML bytes into visible Markdown. Native PHP markerPDF therefore treats Catalog `/Metadata` as a document metadata stream only after PDF stream decoding and XML packet-root selection, while keeping XMP packet text out of visible WordPress paragraphs.

## Behavior

Some malformed or producer-padded metadata streams can contain an empty self-closing `x:xmpmeta` wrapper before the real RDF-bearing XMP packet. Before this slice, the fallback root candidate selected that empty wrapper, parsed it successfully as XML, produced no metadata, and stopped before the current RDF-bearing root.

`PdfMetadataExtractor` now skips empty self-closing `xmpmeta` wrapper candidates when a later non-empty `xmpmeta` or `rdf:RDF` root is present. The accepted document-XMP path promotes the later current title/description/authors/dates, while the rejected XML-stream review path records a redacted XMP summary from the same bounded root. Trailing decoy XMP packets remain excluded, and XMP text remains absent from visible WordPress paragraphs.

## Red-First Evidence

One-off pre-fix extractor check:

```text
array (
)
NULL
```

The fixture had Catalog `/Metadata` pointing to a `/Type /Metadata /Subtype /XML` stream whose decoded bytes started with an empty self-closing `x:xmpmeta` wrapper followed by the current RDF-bearing XMP root. The pre-fix metadata `xmp` array was empty and the promoted title was `NULL`.

## Verification

Focused new behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpSelfClosingRootBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips empty self-closing XMP wrapper before the current metadata root
PASS summarizes rejected XML streams after empty self-closing XMP wrappers

1 test files, 38 assertions, 0 failures
```

Adjacent XMP/metadata sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpCdataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpCommentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpEntityBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangMarkInfoCatalogCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNameTreeAssociatedSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpSelfClosingRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 16 selected test files (root lock skipped)
...
16 test files, 2039 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-self-closing-root-boundary-currentbase.php
```

Emits `self_closing_wrapper_skipped=true`, `packet_boundary_applied=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by a Gutenberg paragraph containing only `Self Closing XMP WordPress Body`.

## Non-Overlap

This does not repeat accepted XMP packet padding/trailing-decoy, comment/DOCTYPE, CDATA, entity rejection, UTF-16, language alternative, qualified value, nested qualifier, typed-node, generation-exact associated-file XMP, trailer-root metadata, encryption, OutputIntent, name-tree, or PDF/A schema slices. The new boundary is only empty self-closing XMP wrapper root selection before a later current XMP/RDF root.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF stream decoder, catalog metadata stream resolver, XMP XML token scanner, DOM metadata parser, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for the no-GPU markerPDF lane.
