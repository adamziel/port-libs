# markerpdf-xmp-metadata-boundary-current-base-20260605T125830Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `a7fcab9938b3f699e7572fbf8e5c7dcf121bd3dc`
- Behavior cluster: native no-GPU XMP metadata parsing for simple text-form Dublin Core `dc:subject` values.

## Source Truth

- PDF Catalog `/Metadata` can point at a `/Type /Metadata /Subtype /XML` XMP packet used as document metadata.
- XMP Dublin Core subjects are keyword metadata. Existing markerPDF lane behavior already splits compact RDF `dc:subject` attributes and `pdf:Keywords` on comma/semicolon boundaries. This slice applies the same boundary to simple child-text `<dc:subject>wordpress, xmp-text-subject; import-review</dc:subject>`.
- `dc:creator` text remains a literal author value, so comma-bearing names such as `Doe, Jane` are not split into separate authors.

## Patch

- `PdfMetadataExtractor::xmpListValues()` now delimiter-splits simple text `dc:subject` values while preserving existing list-container behavior and creator literal handling.
- Added focused current-base tests for accepted document XMP and rejected review-only XML streams.
- Added a WordPress smoke proving split keywords, creator comma preservation, packet-boundary exclusion, visible-text isolation, and no Python/model/external PDF tooling.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTextSubjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL splits simple text XMP dc subject keywords without splitting creator names
Expected: ['wordpress', 'xmp-text-subject', 'import-review']
Actual: ['wordpress, xmp-text-subject; import-review']
FAIL summarizes rejected simple text XMP subject streams with split keyword counts
Expected: 3
Actual: 1
1 test files, 20 assertions, 2 failures
```

Focused run after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpTextSubjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS splits simple text XMP dc subject keywords without splitting creator names
PASS summarizes rejected simple text XMP subject streams with split keyword counts
1 test files, 43 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 1897 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-text-subject-boundary-currentbase.php
```

Emits `title_from_xmp_text=true`, `creator_comma_preserved=true`, `subject_keywords_split=true`, `packet_boundary_applied=true`, `trailing_decoy_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

- Avoids the accepted xref object-stream signed-header slice and prior XMP packet/root namespace/attribute/qualified-value/UTF-16/current-xref metadata work.
- This slice changes only the simple child-text `dc:subject` keyword boundary in native XMP metadata parsing.

## Dependency Closure

- No new support component is needed. The existing native PDF metadata parser, stream decoder, XMP packet boundary scanner, and DOM-based XMP extraction are reused.
- GPU/model OCR, Surya/Texify/Torch, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF direction.

## Root Harness

- Not run - isolated micro-slice.
