# XMP Qualified Value Boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T022012Z`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF imports through pdftext/PDFium-style document extraction boundaries. The native PHP lane owns catalog `/Metadata` XMP extraction before WordPress import.

XMP qualified properties are represented as RDF wrappers where the property value is stored in `rdf:value` and sibling elements are qualifiers. The native parser must therefore import the `rdf:value` string for title, description, authors, keywords, producer, creator tool, and dates without joining qualifier text into WordPress metadata.

PDF source truth remains the catalog `/Metadata` indirect stream boundary: document XMP is promoted only from a `/Type /Metadata /Subtype /XML` stream; other XML streams are review-only and redacted.

References used: Adobe XMP Specification Part 1, sections on qualifiers and allowed RDF forms (`rdf:value`), and PDF Reference 1.7 sections for document catalog `/Metadata` and metadata stream dictionary `/Type /Metadata /Subtype /XML`.

## Red-First Evidence

Before the source edit, the new focused test failed because the current parser read qualifier text with the XMP property value and missed the qualified date value:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts qualified XMP rdf:value text without qualifier leakage
Expected: 'Current Qualified XMP Title'
Actual: 'Current Qualified XMP Titletitle qualifier noise'
FAIL summarizes rejected qualified XMP streams without exposing rdf:value text
Expected: '2026-06-05T01:20:45Z'
Actual: NULL

1 test files, 19 assertions, 2 failures
```

## Implementation

`PdfMetadataExtractor` now reads XMP simple property values, language alternatives, and RDF list items through `rdf:value` when a qualified property wrapper is present. XMP property lookup is also restricted to direct children or attributes of top-level `rdf:RDF/rdf:Description` nodes so nested qualifier elements such as a `pdf:Producer` inside `dc:description` cannot replace the document producer.

The focused fixture covers both accepted document XMP and rejected non-Metadata XML streams. It proves:

- qualified `dc:title`, `dc:description`, `dc:creator`, `dc:subject`, `pdf:Producer`, `xmp:CreatorTool`, `xmp:CreateDate`, and `xmp:MetadataDate` use `rdf:value`;
- qualifier text is excluded from metadata output and visible WordPress paragraphs;
- trailing decoy XMP packets remain excluded by the existing packet boundary;
- rejected XML metadata stream summaries still expose only redacted field names and normalized dates.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts qualified XMP rdf:value text without qualifier leakage
PASS summarizes rejected qualified XMP streams without exposing rdf:value text

1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
...
10 test files, 1198 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
Focused test run: 22 selected test files (root lock skipped)
...
22 test files, 1737 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-qualified-value-boundary-currentbase.php
```

The smoke emitted `title_from_rdf_value=true`, `producer_from_top_level_property=true`, `packet_boundary_applied=true`, `qualifier_text_excluded=true`, `decoy_xmp_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted XMP packet padding, comment/DOCTYPE/CDATA false-root, BOM-less UTF-16, Info encoding fallback, catalog non-Metadata stream rejection, encrypted metadata source priority, associated FileSpec XMP generation, OutputIntent/name-tree, PDF/A schema, or trailer-root/current-xref metadata selection slices. The new bounded behavior is specifically qualified XMP property value extraction and top-level XMP property lookup before WordPress metadata import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF stream decoder, catalog metadata boundary, DOM-based XMP parser, Info fallback, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, PDFium/pypdfium execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
