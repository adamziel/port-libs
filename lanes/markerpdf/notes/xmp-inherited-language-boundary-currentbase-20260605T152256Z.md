# markerPDF XMP Inherited Language Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T152256Z`

Base accepted HEAD: `6b3aab79916239f37aedcd25bf440809e9645e6e`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable-PDF metadata extraction through the PDF text/parser stack rather than OCR/model execution. The no-GPU PHP lane owns the native parser-side XMP metadata boundary before any WordPress import handoff.

Relevant XML/XMP behavior: `xml:lang` is inherited by descendant XML elements. For XMP `rdf:Alt` document metadata, an inherited `xml:lang="x-default"` on the `rdf:Alt` container should select an untagged `rdf:li` default alternative before localized decoy values.

## Behavior

`PdfMetadataExtractor::preferredAltText()` already selected direct `rdf:li xml:lang="x-default"` alternatives. It did not account for inherited XML language values, so an `rdf:Alt xml:lang="x-default"` container with a localized direct sibling first could promote the localized title or description before the inherited default value.

This slice adds inherited XML language lookup for XMP alternatives so WordPress import metadata:

- prefers `rdf:li` values inheriting `xml:lang="x-default"` from an ancestor;
- keeps localized direct-language alternatives out of promoted document title/description metadata;
- preserves rejected non-document XMP stream summaries without leaking localized payload text;
- keeps XMP alternatives out of visible PDF text extraction.

## Red-First Evidence

A current-base probe before the source edit returned localized `fr-FR` decoys for both `dc:title` and `dc:description` when the default value only inherited `xml:lang="x-default"` from the surrounding `rdf:Alt`.

```text
Expected title: Current Inherited Language XMP Title
Actual title before fix: Localized Inherited Language Decoy Title
Expected description: Inherited x-default XMP description wins before WordPress import
Actual description before fix: Localized inherited language description must not become the document summary
```

## Verification

Focused inherited-language XMP boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses inherited x-default xml language in XMP alternatives before WordPress metadata import
PASS summarizes rejected inherited-language XMP streams without leaking localized alternatives

1 test files, 40 assertions, 0 failures
```

Adjacent XMP metadata boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpLangAltBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpNestedQualifierBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpQualifiedValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS uses inherited x-default xml language in XMP alternatives before WordPress metadata import
PASS summarizes rejected inherited-language XMP streams without leaking localized alternatives
PASS prefers uppercase XMP x-default language alternatives before WordPress metadata import
PASS summarizes rejected uppercase x-default XMP streams without leaking alternatives
PASS treats catalog Metadata null as absent before WordPress import
PASS keeps direct catalog Metadata dictionaries review-only before WordPress import
PASS keeps unresolved catalog Metadata references as fail-closed review metadata
PASS records unreadable XMP metadata stream filters without promoting payload text
PASS extracts direct XMP RDF collection values without nested qualifier list leakage
PASS summarizes rejected XMP streams using direct RDF collection counts only
PASS extracts qualified XMP rdf:value text without qualifier leakage
PASS summarizes rejected qualified XMP streams without exposing rdf:value text

5 test files, 213 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-inherited-language-boundary-currentbase.php
```

The smoke emits `title_from_inherited_x_default=true`, `description_from_inherited_x_default=true`, `localized_alternative_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfMetadataXmpInheritedLanguageBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xmp-inherited-language-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xmp-inherited-language-boundary-currentbase.php

php -r '$p="lanes/markerpdf/lane-status.json"; json_decode((string) file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

All syntax, JSON, and whitespace checks passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct uppercase `xml:lang="X-DEFAULT"` alternatives, XMP packet boundary handling, self-closing/empty XMP root handling, DTD/entity hardening, namespace spoofing, qualified `rdf:value` extraction, nested qualifier list handling, metadata reference fail-closed behavior, unreadable metadata-stream filter review, xref metadata selection, or model/OCR metadata extraction.

The new bounded behavior is specifically XML language inheritance while selecting XMP `rdf:Alt` document metadata values.

## Dependency Closure

No new support component is needed. This reuses the native PDF object/stream scanner, `DOMDocument` XMP parsing, focused PHP tests, and a WordPress smoke. GPU/OCR/model execution, PDFium, Python, external PDF tools, and live upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
