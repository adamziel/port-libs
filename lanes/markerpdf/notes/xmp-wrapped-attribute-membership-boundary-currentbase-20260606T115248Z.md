# XMP Wrapped Attribute Membership Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T115248Z`

Accepted base: `7b9b6e5a2c6885b2398accee1db59fa1d0384094`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF document
metadata before OCR/layout/model stages. In this native PHP/no-GPU lane,
catalog `/Metadata` XMP streams are handled as RDF/XML document metadata when
the stream is a `/Type /Metadata` and `/Subtype /XML` catalog metadata stream.

RDF/XML supports collection membership properties such as `rdf:_1`, `rdf:_2`,
and `rdf:_10` as list values. The existing current-base parser already handles
those attributes on same-packet referenced resource nodes. This slice covers
the adjacent inline resource-wrapper boundary where `dc:creator` or
`dc:subject` contains an inline `rdf:Description` carrying ordered membership
attributes instead of a child `rdf:Bag`, `rdf:Seq`, `rdf:Alt`, or external
same-packet resource reference.

## Red-First Evidence

Before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpWrappedAttributeMembershipBoundaryCurrentBaseTest.php`

Result: `1 test files / 17 assertions / 2 failures`.

Failures:

- accepted document authors fell back to `/Info` author instead of the ordered
  inline wrapped XMP creator values;
- rejected-stream summary omitted `authors` and `keywords` because the inline
  wrapped membership attributes were not counted as list metadata.

## Implementation

`PdfMetadataExtractor::xmpRdfCollectionTextValues()` now keeps its existing
order:

- direct `rdf:li` and RDF membership-property child elements;
- RDF containers and resource-wrapped containers;
- same-packet referenced resource membership attributes.

When those paths do not yield values, it now checks inline child
`rdf:Description` resource wrappers for `rdf:_n` membership attributes and
returns them in numeric order. This keeps unreferenced top-level resources and
trailing packet decoys excluded while promoting inline wrapped document list
values before `/Info` fallback.

## Verification

Focused wrapped attribute-membership boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpWrappedAttributeMembershipBoundaryCurrentBaseTest.php`

Result: `1 test files / 46 assertions / 0 failures`.

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadataXmp.*CurrentBaseTest\.php$' | sort)`

Result: `43 test files / 2760 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-wrapped-attribute-membership-boundary-currentbase.php`

Result: passed. The smoke emits `title_from_xmp=true`,
`authors_from_inline_wrapped_membership=true`,
`keywords_from_inline_wrapped_membership=true`,
`info_author_not_promoted=true`, `packet_boundary_applied=true`,
`trailing_decoy_excluded=true`, `unreferenced_resource_excluded=true`,
`visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpWrappedAttributeMembershipBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-wrapped-attribute-membership-boundary-currentbase.php`

Result: no syntax errors.

Lane status JSON:

`php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'`

Result: `lane-status.json valid`.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2532 -> 2534` from two focused TestRunner PASS cases.
- `wordpressScenarios`: `2150 -> 2151` from the new WordPress inline wrapped
  attribute-membership XMP boundary smoke.
- Adds 46 focused assertions after the red-first failure.
- Does not update root progress/dashboard files.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-metadata XML stream rejection, packet padding,
complete-packet fallback, unpaired-begin handling, instruction filtering,
DTD/entity rejection, CDATA/comment root selection, namespace wrapper
filtering, same-prefix namespace packets, self-closing/empty roots, compact RDF
attributes, language alternatives, qualified/nested values,
resource-reference fragment targets, referenced RDF membership attributes,
nodeID blank-node resolution, split descriptions, sparse lists,
resource-wrapped `rdf:Bag`/`rdf:Seq`/`rdf:Alt` list containers, typed-node
`rdf:about=""` promotion, external non-empty `rdf:about` filtering, FileSpec
XMP generation exactness, encrypted metadata source priority, OutputIntent/
PieceInfo/name-tree metadata review, xref repair, fonts, images, annotations,
forms, OCR, or model execution.

The bounded behavior is only inline child `rdf:Description` wrappers carrying
`rdf:_n` membership attributes inside XMP list properties.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object
scanner, stream decoder, catalog metadata boundary validation, XMP XML packet
boundary logic, DOM-based RDF parser, Info fallback, rejected-stream summary
path, plain-text extractor, and WordPress smoke renderer. Full upstream
markerPDF parity for scanned/OCR/model-driven layouts remains intentionally
out of scope under the no-GPU directive.
