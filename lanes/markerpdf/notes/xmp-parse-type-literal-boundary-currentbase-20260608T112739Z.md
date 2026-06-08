# markerpdf-xmp-metadata-boundary-current-base-20260608T112739Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `d4e1c6e37b4f2ee07da5d4369183d41cf268bfc1`
- Behavior cluster: native XMP RDF/XML `rdf:parseType="Literal"` metadata extraction and non-document XML stream review.

## Source Truth

XMP uses RDF/XML. `rdf:parseType="Literal"` means child XML is the property value, so markerPDF should collapse its child text into scalar metadata only when the catalog `/Metadata` object is a real `/Type /Metadata /Subtype /XML` stream. XML-like EmbeddedFile streams remain review-only and must not expose the literal payload.

The implementation deliberately preserves the existing structured-property boundary: child elements without `rdf:value`, `rdf:resource`, `rdf:nodeID`, or `rdf:parseType="Literal"` are still not promoted as scalar metadata.

## Evidence

- Red-first probe on the accepted base showed a valid document XMP packet with Literal title/creator/producer/date fell back to trailer Info metadata and left `xmp` empty.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpParseTypeLiteralBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 45 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-xmp-parse-type-literal-currentbase.php`
  - Result: exits `0`; output comment reports Literal XML tags redacted, trailing packet values excluded, rejected EmbeddedFile XML review-only, no Python/models/external PDF tools.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP DOM/XMP parsing already in `PdfMetadataExtractor` and does not invoke OCR, Surya, Texify, Torch, raster rendering, multiprocessing, live services, or external PDF tools.

## Non-Overlap

This does not repeat the existing XMP packet begin/end, unsafe DTD/entity, UTF-16/declared encoding, `parseType="Collection"`, `parseType="Resource"`/`rdf:value`, resource reference, nodeID, LangAlt, typed-node, or catalog `/Metadata` stream-type boundary slices. It adds the missing RDF/XML Literal value form only.

## Next

Continue native no-GPU markerPDF work on non-overlapping metadata, stream filter, xref repair, annotations/forms, fonts/CMaps, page geometry, image metadata, or supplied-boundary table/equation handoffs.
