# markerPDF XMP rdf:nodeID metadata boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T202248Z`

Base: `9aa35d009f07fabee9a32a57e5e751856e526db5`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF metadata
through the PDF parser/text extraction boundary before OCR/model stages. The
native no-GPU PHP lane owns catalog `/Metadata` XMP parsing for WordPress
document metadata.

The W3C RDF/XML syntax specification defines `rdf:nodeID` as a document-scoped
blank-node identifier that can be used on a node element or on a property
element in place of `rdf:resource`. This slice maps that RDF/XML form into the
bounded XMP parser without promoting blank-node targets as separate document
roots.

## Behavior

`PdfMetadataExtractor` now resolves same-packet RDF `rdf:nodeID` blank-node
references for XMP scalar and list fields:

- `dc:title`, `dc:description`, `xmp:CreateDate`, `xmp:MetadataDate`,
  `xmp:CreatorTool`, and `pdf:Producer` can resolve from blank-node targets;
- `dc:creator` and `dc:subject` can resolve `rdf:Seq` / `rdf:Bag` targets while
  preserving author and keyword item boundaries;
- unreferenced blank-node targets stay non-root review nodes;
- XMP stream payload text remains out of visible WordPress paragraphs.

## Red-First Evidence

After adding `PdfMetadataXmpNodeIdBoundaryCurrentBaseTest.php` and before the
source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNodeIdBoundaryCurrentBaseTest.php`

Result: `1 test files / 15 assertions / 2 failures`

Failures:

- accepted document XMP fell back to trailer Info title instead of resolving
  `dc:title rdf:nodeID`;
- rejected XML stream summary saw only the unreferenced blank-node producer
  decoy, missing title, description, dates, authors, and keywords.

## Verification

Focused nodeID boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpNodeIdBoundaryCurrentBaseTest.php`

Result: `1 test files / 47 assertions / 0 failures`

Adjacent XMP metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `33 test files / 2267 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-nodeid-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_nodeid=true`,
`authors_from_nodeid_seq=true`, `keywords_from_nodeid_bag=true`,
`unreferenced_node_decoy_excluded=true`, `trailing_decoy_excluded=true`,
`visible_text_excludes_xmp=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2194 -> 2196` from two new focused TestRunner PASS cases.
- `wordpressScenarios`: `1890 -> 1891` from the new WordPress nodeID XMP smoke.

## Non-Overlap

This does not repeat accepted compact RDF attributes, `rdf:resource` fragment
references, resource-wrapped lists, sparse list fallback, split descriptions,
typed-node extraction, qualified `rdf:value` values, language alternatives,
packet begin/end selection, namespace spoofing, entity rejection, UTF-16 or
declared-encoding decoding, metadata-stream object boundaries, XMP/Info date
normalization, encrypted metadata source policy, PieceInfo/associated-file XMP
review, xref repair, fonts/CMaps, image filters, annotations, forms, OCR, or
model execution.

The bounded behavior is only same-packet RDF `rdf:nodeID` blank-node reference
resolution for document XMP metadata and rejected-stream summaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
parser, Flate stream decoder, DOM-based XMP parser, metadata review summary
path, text extractor, and WordPress smoke pattern. Full upstream OCR/model,
PDFium, Surya, Texify, Torch, and external renderer parity remains
intentionally out of scope under the current no-GPU markerPDF directive.
