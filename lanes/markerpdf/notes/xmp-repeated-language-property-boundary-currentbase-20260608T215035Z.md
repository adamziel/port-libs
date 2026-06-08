# markerPDF XMP Repeated Language Property Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T215035Z`

Base accepted HEAD: `6f8463809fe932bed047f1bc503ab1bca68687f8`

## Source Truth

Upstream `sddai/markerPDF` routes searchable-PDF metadata through the PDF parser
and document metadata boundary before model-dependent layout or OCR work. The
no-GPU PHP lane owns this native parser boundary for WordPress import.

XMP `rdf:Alt` containers are the normal language-alternative shape, but real
XMP producers can also emit repeated simple Dublin Core properties such as
`dc:title` or `dc:description` with `xml:lang` attributes on sibling elements.
For document metadata import, a repeated sibling marked `xml:lang="x-default"`
should win before localized siblings, and none of the XMP packet text should
be promoted into visible PDF body text.

## Behavior

`PdfMetadataExtractor::xmpSingleValue()` previously selected the first repeated
simple property value for `dc:title` and `dc:description` when those values
were not wrapped in an `rdf:Alt` container. That promoted localized decoys when
the first sibling used a language such as `fr-FR` and a later sibling carried
`xml:lang="x-default"`.

This slice adds repeated-property language selection for XMP fields that prefer
language alternatives:

- repeated simple XMP property siblings are scanned before fallback selection;
- direct or inherited `xml:lang="x-default"` wins case-insensitively;
- if no default-language sibling exists, the first usable sibling remains the
  fallback;
- rejected non-document XML streams still expose only redacted XMP review
  summaries and do not leak localized siblings.

## Red-First Evidence

Before the source edit, the new focused test failed because the first localized
title sibling won before the default-language sibling.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRepeatedLanguagePropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL prefers x-default repeated XMP language properties before WordPress metadata import (...Test.php)
Values are not identical
Expected: 'Current Repeated Language XMP Title'
Actual: 'Localized Repeated Language Decoy Title'
PASS summarizes rejected repeated-language XMP streams without leaking localized siblings

1 test files, 26 assertions, 1 failures
```

## Verification

Focused repeated-language XMP boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRepeatedLanguagePropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS prefers x-default repeated XMP language properties before WordPress metadata import
PASS summarizes rejected repeated-language XMP streams without leaking localized siblings

1 test files, 46 assertions, 0 failures
```

Adjacent XMP metadata subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpRepeatedLanguagePropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpAttributeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpStructuredPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpParseTypeLiteralBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpParseTypeCollectionBoundaryCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
PASS extracts compact RDF XMP attributes without splitting comma-bearing creator names
PASS summarizes rejected compact RDF attribute XMP streams with redacted creator counts
PASS uses inherited x-default xml language in XMP alternatives before WordPress metadata import
PASS summarizes rejected inherited-language XMP streams without leaking localized alternatives
PASS prefers uppercase XMP x-default language alternatives before WordPress metadata import
PASS summarizes rejected uppercase x-default XMP streams without leaking alternatives
PASS promotes RDF parseType Collection XMP list nodes as ordered metadata values
PASS summarizes rejected XMP streams with parseType Collection counts only
PASS extracts RDF parseType Literal XMP text values without XML tag leakage
PASS summarizes rejected parseType Literal XML streams without exposing literal payload text
PASS extracts qualified XMP rdf:value text without qualifier leakage
PASS summarizes rejected qualified XMP streams without exposing rdf:value text
PASS prefers x-default repeated XMP language properties before WordPress metadata import
PASS summarizes rejected repeated-language XMP streams without leaking localized siblings
PASS keeps structured XMP properties without rdf value out of document metadata
PASS summarizes rejected structured XMP streams without private scalar fields

8 test files, 355 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-repeated-language-property-currentbase.php
```

The smoke exits 0 and emits `title_from_repeated_x_default:true`,
`description_from_repeated_x_default:true`, `localized_sibling_excluded:true`,
`trailing_packet_excluded:true`, `visible_text_excludes_xmp:true`,
`executes_python_or_models:false`, and
`executes_external_pdf_tools:false`.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfMetadataXmpRepeatedLanguagePropertyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfMetadataXmpRepeatedLanguagePropertyBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xmp-repeated-language-property-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xmp-repeated-language-property-currentbase.php

php -r 'json_decode((string) file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode((string) file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0 with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP tests add `2` PASS cases and `46` direct assertions for
  repeated simple XMP language-property selection.
- The adjacent XMP subset passes with `8` test files and `355` assertions.
- WordPress scenarios add one smoke for repeated simple XMP title/description
  language siblings before import.
- The upstream manifest maps two native PDF XMP metadata boundary behaviors.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, Flate stream decoder, `DOMDocument` XMP parser, Info fallback,
redacted metadata-stream review summaries, focused PHP tests, and a WordPress
smoke. Python, pdftext, pypdfium, Surya, Texify, Torch, OCR/model execution,
image raster execution, online services, and external PDF tools are not needed
or run for this slice.

## Non-Overlap

This does not repeat accepted XMP packet bounding, direct `rdf:Alt` language
selection, uppercase `x-default` handling, inherited language handling inside
`rdf:Alt`, compact XMP attributes, qualified `rdf:value` extraction,
parseType Resource/Literal/Collection handling, resource references, namespace
spoofing, typed nodes, duplicate RDF roots, metadata-stream role/filter
reviews, encrypted metadata preflight, annotations, forms, images, tables,
OCR/model behavior, or exact upstream model benchmark parity.

The bounded behavior is only repeated simple XMP title/description sibling
properties carrying `xml:lang` alternatives in native searchable-PDF metadata.

## Next Task

Continue with non-overlapping native markerPDF parser/converter boundaries:
font/CMap widths, stream filters, xref repair, annotations, forms, page
geometry, image/filter metadata, or supplied-boundary table/equation handoffs
under the no-GPU markerPDF scope.
