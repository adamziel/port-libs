# markerpdf XMP Duplicate Resource-Reference Boundary

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T135230Z`

Accepted base: `95ed9a719a03101e72b33de7de15d86db46d9a80`

## Source truth

PDF catalog `/Metadata` XMP packets use RDF/XML. Same-packet `rdf:resource="#id"` and `rdf:nodeID="id"` references are safe to dereference only when exactly one document-level target node matches the ID. If multiple same-packet nodes share the same `rdf:about`, `rdf:ID`, `xml:id`, or `rdf:nodeID`, the PHP importer now treats the reference as ambiguous and review-only instead of silently selecting the first target.

This keeps stale repair nodes from overriding WordPress import metadata. Ambiguous XMP title, description, and creator references fall back to the PDF Info dictionary while direct XMP fields such as keywords, producer, creator tool, and dates remain accepted.

## Evidence

Red-first focused run before the extractor change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateResourceReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 15 assertions, 2 failures`

Failure cause: duplicate same-packet XMP target nodes resolved to the first stale target, so title/description/authors came from stale XMP payload instead of Info fallback and the rejected-stream summary exposed those fields as imported metadata.

Passing focused run after the extractor change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDuplicateResourceReferenceBoundaryCurrentBaseTest.php`

Result: `1 test files, 51 assertions, 0 failures`

Adjacent XMP current-base family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `66 test files, 3089 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-duplicate-resource-reference-boundary-currentbase.php`

Result: exits 0 and reports `info_fallback_used_for_ambiguous_title=true`, `info_fallback_used_for_ambiguous_author=true`, `xmp_direct_keywords_preserved=true`, `ambiguous_reference_count=3`, `target_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP `DOMDocument` XMP parser and `PdfMetadataExtractor` metadata path. It does not run Python, OCR, GPU/model code, raster rendering, external PDF tools, network services, or live provider tests.

Root harness: not run - isolated micro-slice.

## Next

Continue with non-overlapping native markerPDF metadata/parser boundaries such as XMP/Info conflict resolution, xref repair, font/CMap extraction, annotations/forms, page geometry, or supplied-boundary table/equation handoffs.
