# AcroForm Direct Field Dictionary Boundary - 2026-06-05

Slice: `markerpdf-acroform-fields-boundary-current-base-20260605T164746Z`

Base: `90a62a42c6f66bee7e7e49f53c431a1b66636b98`

## Source Truth

The no-GPU markerPDF lane stays on native searchable-PDF parser behavior. PDF AcroForm field trees are rooted at the catalog `/AcroForm /Fields` array, and field `/Kids` arrays can carry child field dictionaries and widget annotations. This slice covers the boundary where those arrays contain direct field dictionaries rather than indirect `N G R` references.

The implementation reuses the existing native dictionary/array scanner and field walker. It materializes only top-level direct dictionaries from `/Fields` and field `/Kids` arrays into generation-zero in-memory synthetic objects, then lets the existing AcroForm hierarchy, inheritance, widget, action, and visible-text review code process them. Literal strings, comments, nested arrays, nested dictionaries, and detached field-like objects remain decoys.

## Red-First Evidence

Before the patch, this focused probe returned no AcroForm fields for a catalog `/Fields` array containing a direct text field dictionary:

```bash
php -r 'require "tools/bootstrap.php"; $pageText="BT /F1 12 Tf 72 720 Td (Visible direct dict body) Tj ET"; $pdf="%PDF-1.7\n"."1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm 5 0 R >>\nendobj\n"."2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"."3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"."4 0 obj\n<< /Length ".strlen($pageText)." >>\nstream\n{$pageText}\nendstream\nendobj\n"."5 0 obj\n<< /Fields [<< /FT /Tx /T (direct.root) /V (direct value) >>] /NeedAppearances true >>\nendobj\n%%EOF"; $form=(new PortLibs\MarkerPDF\PdfAcroFormExtractor())->extractForm($pdf); echo json_encode(array_column($form["fields"], "name"), JSON_UNESCAPED_SLASHES), "\n";'
```

Output:

```text
[]
```

## Implemented Boundary

- `PdfAcroFormExtractor` now rewrites the current AcroForm `/Fields` array and reachable field `/Kids` arrays in memory when a top-level direct field dictionary appears.
- Synthetic direct dictionaries receive fresh object numbers above the current object high-water mark and generation `0`, preserving existing exact-generation checks for real indirect references.
- Existing field hierarchy/value inheritance logic now reviews direct root fields and direct child fields while retaining page-owned indirect widget metadata.
- Nested direct dictionaries inside decoy arrays/dictionaries/comments are not materialized.

## Verification

```bash
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsDirectDictionaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-dictionary-currentbase.php
```

All changed PHP files reported no syntax errors.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectDictionaryBoundaryCurrentBaseTest.php
```

Result: `1 test files, 68 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectDictionaryBoundaryCurrentBaseTest.php
```

Result: `2 test files, 570 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
```

Result: `38 test files, 3295 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-dictionary-currentbase.php
```

Smoke flags include `direct_fields_materialized=true`, `direct_kids_materialized=true`, `direct_child_value_overrides_parent=true`, `page_annotation_indexes_preserved=true`, `array_decoy_fields_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted AcroForm slices for page-owned widget repair, direct widget entries in `/Fields`, indirect `/Fields` and `/Kids` arrays, generation-exact references, duplicate-key boundaries, token-aware array decoys, alternate/mapping names, indirect scalar operands, comment-only widget subtype markers, wrong-page `/P`, unowned parent repair rejection, XFA, signatures, actions, or appearance execution.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF object scanning, token-aware array/dictionary parsing, AcroForm field-tree traversal, widget metadata extraction, and plain text extraction. OCR, Surya/Texify/Torch models, PDFium/pypdfium execution, Poppler, Ghostscript, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF boundary.
