# markerpdf-xmp-metadata-boundary-current-base-20260606T175209Z

Base accepted HEAD: `bd271365a39bc8cc84f04507c8a4161eee64c7c5`

## Source Truth

- Upstream markerPDF uses PDFium/pdftext metadata/text extraction before model execution; this no-GPU slice maps the native searchable-PDF metadata boundary only.
- PDF Catalog `/Metadata` XMP is document metadata. A valid XMP packet exposes a document-level `rdf:RDF` root under `x:xmpmeta` or as the root itself; later sibling RDF roots in the same packet are treated as stale/repaired metadata and cannot fill omitted WordPress document fields.

## Behavior

- `PdfMetadataExtractor` now resolves document-level XMP traversal through the first active `rdf:RDF` root.
- Same-packet resource/node-id lookup also stays inside that selected RDF root, so stale duplicate RDF siblings cannot provide authors, descriptions, keywords, producer, creator-tool, or referenced resource values.
- Complete xpacket begin/end packet candidates are considered before the raw full-stream XML candidate, so `packet_boundary_applied` is recorded even when the full stream is well-formed with processing instructions around one root.
- Trailer Info fallback remains preserved for fields omitted by the selected XMP root.

## Evidence

Red-first focused run after adding the focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateRdfRootBoundaryCurrentBaseTest.php
=> 1 test files / 16 assertions / 2 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateRdfRootBoundaryCurrentBaseTest.php
=> 1 test files / 43 assertions / 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
=> 47 test files / 2968 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-duplicate-rdf-root-boundary-currentbase.php --self-test
=> title_from_first_rdf_root=true, packet_boundary_applied=true,
   info_author_fallback_preserved=true, info_producer_fallback_preserved=true,
   stale_rdf_author_excluded=true, stale_rdf_description_excluded=true,
   stale_rdf_keywords_excluded=true, visible_text_excludes_xmp=true
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted malformed CMap WMode filter-boundary slice or prior XMP packet begin/end, instruction attribute, unpaired begin, empty-root, namespace, resource-reference, or catalog `/Metadata` operand/type-boundary slices. The new coverage is duplicate document-level RDF sibling selection inside one otherwise valid active XMP packet.

## Dependency Closure

No new support component is needed. The slice reuses native PDF stream decoding and `DOMDocument` XMP parsing. No Python, GPU/model execution, pypdfium, pdftext, external PDF tools, or live services were run.
