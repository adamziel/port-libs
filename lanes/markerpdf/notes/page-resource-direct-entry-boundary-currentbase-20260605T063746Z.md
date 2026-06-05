# markerPDF page resource direct-entry boundary

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T063746Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, with pdftext/PDFium handling low-level page resources before OCR/layout/model stages.
- PDF page `/Resources` are inheritable page-tree attributes, but `/Font`, `/XObject`, `/Properties`, and similar resource-category dictionaries contain real resource objects. Malformed direct array/name/string entries in those dictionary-backed categories should not be advertised as usable WordPress import resources. Direct `/ColorSpace` name/array operands remain valid resource entries.

## Behavior

`PdfPagePropertyExtractor::resourceSubdictionaryEntryIsResolvable()` now filters direct entries by resource-category shape before emitting page-boundary resource-name metadata:

- `/Font`, `/XObject`, `/Properties`, `/ExtGState`, `/Pattern`, and `/Shading` direct entries must be direct dictionaries or resolvable indirect dictionaries/streams.
- `/ColorSpace` direct name and array operands remain reportable.
- Native text extraction still uses the valid inherited font, form XObject, and ActualText property resources, while malformed direct resource entries stay out of visible WordPress paragraphs and review metadata.

Focused fixture boundary:

```text
/Resources <<
  /Font << /BadArray [99 0 R] /BadName /Helvetica /BadString (Font decoy review leak) /Fvalid 5 0 R >>
  /XObject << /BadArray [6 0 R] /BadName /Image /ValidForm 6 0 R >>
  /Properties << /BadArray [7 0 R] /BadName /Artifact /GoodActual 7 0 R >>
  /ColorSpace << /CS1 /DeviceRGB /CS2 [/Indexed /DeviceRGB 0 <00>] >>
>>
```

Expected review metadata now reports `font_names=["Fvalid"]`, `xobject_names=["ValidForm"]`, `properties_names=["GoodActual"]`, and `color_space_names=["CS1","CS2"]`.

## Red-First Probe

Before the source change, the focused test failed because page-boundary metadata reported malformed direct `/Font` entries:

```text
Expected: array (
  0 => 'Fvalid',
)
Actual: array (
  0 => 'BadArray',
  1 => 'BadName',
  2 => 'BadString',
  3 => 'Fvalid',
)
```

## Evidence

Focused page-resource test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS excludes malformed direct inherited resource entries from page review metadata while preserving valid resources
1 test files, 126 assertions, 0 failures
```

Adjacent page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1084 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-direct-entry-boundary-currentbase.php
```

The smoke emits `malformed_direct_entries_excluded=true`, `visible_text_excludes_review_decoys=true`, `font_names=["Fvalid"]`, `xobject_names=["ValidForm"]`, `properties_names=["GoodActual"]`, `color_space_names=["CS1","CS2"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted page `/Resources` null inheritance, explicit empty resource dictionaries, malformed page-resource references, generation-mismatched page resources, escaped `/Kids`, top-level `/Parent` lookup, resource category stream boundaries, Form XObject null resources, Form-local marked-content properties, image XObject rendering, OCR/model execution, or external PDF tool behavior.

The bounded behavior is only direct malformed resource entries inside inherited page resource category dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page-tree inherited resource lookup, dictionary parser, resource-category metadata extractor, text extractor, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
