# XMP Typed Node Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T043233Z`

Accepted base: `5771f733e9e3256de06e48cb643fff27796d43dd`

## Source truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF document metadata through PDF/PDFium-style metadata extraction before OCR/layout/model stages.
- XMP is RDF/XML. The top-level node elements inside the document-level `rdf:RDF` packet may be ordinary `rdf:Description` nodes or typed RDF node elements. Both are document metadata resources. Nested RDF payloads inside private qualifier/review properties are not top-level document resources and must not override the document title, producer, author list, keywords, or rejected-stream summaries.

## Red-first evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php
```

Before the source change, the focused fixture failed because `PdfMetadataExtractor` only scanned `rdf:Description` elements and accepted a nested private RDF decoy instead of the top-level typed RDF node:

- Expected title: `Current Typed Node XMP Title`
- Actual title: `Nested RDF Decoy XMP Title`
- Rejected-stream summary expected 8 typed-node metadata fields, but only reported decoy `title` and `producer`.

## Implementation

- `PdfMetadataExtractor::xmpTopLevelDescriptions()` now scans document-level `rdf:RDF` packet roots and returns direct resource node elements.
- Direct `rdf:Description` children are still accepted.
- Direct non-RDF typed node elements are now accepted as top-level XMP metadata resources.
- Nested `rdf:RDF` packets under private/qualifier properties are excluded from document metadata resource selection.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 45 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$|/PdfMetadataExtractorTest\.php$' | sort)
```

Result: `14 test files, 1373 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-xmp-typed-node-boundary-currentbase.php
```

Result: emitted `typed_node_metadata_imported=true`, `attribute_shorthand_imported=true`, `nested_rdf_decoy_excluded=true`, `trailing_packet_excluded=true`, `visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Adds 2 focused PASS cases in `PdfMetadataXmpTypedNodeBoundaryCurrentBaseTest.php`.
- Adds 45 focused assertions.
- Adds 1 WordPress smoke scenario for XMP typed-node metadata import boundaries.
- Does not update root progress/dashboard files.

## Non-overlap

This does not repeat accepted XMP packet padding, CDATA/comment root-token bounds, DTD/entity fail-closed handling, UTF-16 decoding, LangAlt fallback, qualified `rdf:value`, nested qualifier list filtering, XMP/Info encoding fallback, encrypted metadata source priority, current trailer/root xref selection, associated-file XMP review, PieceInfo private XMP review, or PDF/A OutputIntent association. The bounded behavior is specifically top-level typed RDF node elements in document XMP plus exclusion of nested RDF decoys from metadata resource selection.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, XMP XML candidate boundary logic, DOM-based RDF parser, metadata merger, rejected-stream summary path, and WordPress smoke renderer. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
