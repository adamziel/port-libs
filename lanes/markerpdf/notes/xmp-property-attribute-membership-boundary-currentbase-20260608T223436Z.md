# XMP Property Attribute Membership Boundary

## Scope

Implemented the native XMP/RDF boundary for container membership attributes
declared directly on document metadata property elements such as `dc:title`,
`dc:creator`, `dc:description`, and `dc:subject`.

PDF/XMP writers can encode ordered values with RDF container membership
properties (`rdf:_1`, `rdf:_2`, ...) without wrapping them in an explicit
`rdf:Bag`, `rdf:Seq`, `rdf:Alt`, or referenced resource node. The markerPDF
extractor already handled membership attributes on containers, wrapped nodes,
and referenced resources. This slice adds the missing direct-property case so
WordPress imports promote the ordered metadata values while excluding nested
qualifier/decoy child elements.

## Source Truth

- RDF/XML container membership properties are ordered by their numeric suffix.
- Catalog `/Metadata` still has to be a `/Type /Metadata /Subtype /XML`
  stream before parsed XMP can become document metadata.
- Rejected non-metadata XML streams may keep review-only counts and field
  names, but must not include payload text.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPropertyAttributeMembershipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts direct RDF membership attributes on XMP metadata properties
Expected: 'Current Property Attribute XMP Title'
Actual: 'Property Attribute Info Title'
FAIL summarizes rejected direct XMP property membership attributes without text leakage
Expected field_names included title and description.
Actual field_names omitted title and description.
1 test files, 15 assertions, 2 failures
```

## Implementation

`PdfMetadataExtractor::xmpRdfCollectionItems()` now checks for direct
`rdf:_n` membership attributes on the metadata property element before
falling through to child `rdf:li`, parseType collections, explicit containers,
wrapped descriptions, or resource references.

The change is intentionally narrow: it reuses the existing ordered membership
attribute parser and the existing text/redaction paths.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPropertyAttributeMembershipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts direct RDF membership attributes on XMP metadata properties
PASS summarizes rejected direct XMP property membership attributes without text leakage
1 test files, 52 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPropertyAttributeMembershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpAltAttributeMembershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpContainerAttributeMembershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpAttributeMembershipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpWrappedAttributeMembershipBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS promotes RDF Alt membership attributes from document XMP before WordPress import
PASS summarizes rejected RDF Alt membership attributes without text leakage
PASS resolves RDF membership attributes on referenced XMP list resources
PASS summarizes rejected XMP streams with attribute membership list counts only
PASS extracts XMP RDF container attribute membership lists before WordPress import
PASS summarizes rejected XMP container attribute membership streams without text leakage
PASS extracts direct RDF membership attributes on XMP metadata properties
PASS summarizes rejected direct XMP property membership attributes without text leakage
PASS promotes RDF membership attributes on inline wrapped XMP list resources
PASS summarizes rejected XMP streams with inline wrapped attribute membership counts only
5 test files, 244 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-property-attribute-membership-currentbase.php
```

The smoke exits `0` and emits
`direct_property_membership_extracted=true`,
`qualifier_text_redacted=true`,
`xmp_metadata_not_visible_text=true`,
`rejected_text_values_redacted=true`,
`rejected_xmp_not_visible_text=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF
object/stream decoder, XMP packet boundary parser, DOM-based RDF reader, and
review-only metadata redaction pipeline. No OCR, GPU models, raster rendering,
JavaScript/action execution, provider services, or external PDF tools are used.

## Non-Overlap

This does not repeat accepted XMP attribute scalar extraction, child
`rdf:Alt` membership attributes, referenced membership resources, wrapped
membership resources, container child membership attributes, parseType
collection/literal handling, resource/nodeID target resolution, duplicate
resource target rejection, catalog `/Metadata` duplicate/null/extra-operand
boundaries, or XMP packet begin/end boundary work. The bounded behavior is
only direct `rdf:_n` membership attributes on document metadata property
elements.
