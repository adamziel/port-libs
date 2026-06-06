# markerpdf-xmp-metadata-boundary-current-base-20260606T051903Z

Base accepted HEAD: `23932dd761e9b54b9c5be6a67898bcd0727918e3`.

Implemented a native no-GPU XMP metadata boundary in `PdfMetadataExtractor`: RDF membership attributes such as `rdf:_1`, `rdf:_2`, and `rdf:_10` on same-packet referenced blank-node/resource descriptions now resolve as ordered list values for XMP `dc:creator` and `dc:subject` when child `rdf:li` elements are absent. This preserves upstream RDF/XML metadata semantics for searchable PDFs while keeping unreferenced resource rows, trailing XMP packets, and raw XMP strings out of visible WordPress paragraphs.

Red-first evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAttributeMembershipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves RDF membership attributes on referenced XMP list resources
FAIL summarizes rejected XMP streams with attribute membership list counts only
1 test files, 17 assertions, 2 failures
```

Passing focused evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpAttributeMembershipBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves RDF membership attributes on referenced XMP list resources
PASS summarizes rejected XMP streams with attribute membership list counts only
1 test files, 46 assertions, 0 failures
```

Adjacent family evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
37 test files, 1650 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-attribute-membership-boundary-currentbase.php
```

The smoke emits `authors_from_attribute_membership=true`, `keywords_from_attribute_membership=true`, `info_author_not_promoted=true`, `trailing_decoy_excluded=true`, `unreferenced_resource_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure: no new support component is needed. This reuses the existing native PDF object/stream/XMP parser and focused PHP test runner. GPU/model OCR, Surya, Texify, Torch, PDF raster execution, and exact upstream model benchmark parity remain intentionally out of scope for markerPDF under the current no-GPU directive.

Root harness: not run - isolated micro-slice.
