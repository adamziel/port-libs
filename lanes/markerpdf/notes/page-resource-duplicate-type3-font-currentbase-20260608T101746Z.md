# markerPDF Page Resource Duplicate Type3 Font Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T101746Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T101746Z`
Base accepted HEAD: `d8a224cc5378b08ae8488e7e1fb7e96812aac3f2`

## Source Truth

Upstream markerPDF delegates searchable PDF extraction to PDF parser layers before OCR/model execution. In the native no-GPU PHP boundary, inherited page `/Resources` dictionaries are parser lookup roots. Duplicate resource names inside a `/Font` subdictionary are malformed for the lane's existing fail-closed resource policy: normal ToUnicode font-map lookup and page-resource review already suppress duplicate names, and Type3 CharProc image review must use the same boundary so a stale or duplicate `/Fdup` resource cannot trigger review rows for glyph private image payloads.

## Change

- `PdfTextExtractor::type3FontResourceReferencesForResourceOwnerBody()` now applies `duplicateTopLevelResourceEntryNames()` before collecting indirect or direct Type3 font resources.
- A duplicated inherited `/Font << /Fdup ... /Fdup ... >>` no longer creates Type3 CharProc Image XObject review rows, while visible text remains raw/fail-closed and payload bytes stay out of WordPress paragraphs.
- Added a focused WordPress smoke for duplicate inherited Type3 font resource names before CharProc image review.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate inherited Type3 font resource names before CharProc image review
Values are not identical
Expected: 0
Actual: 1

1 test files, 3 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate inherited Type3 font resource names before CharProc image review

1 test files, 19 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateType3FontCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcRepeatBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcResourceTailBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS records used Type3 CharProc Image XObject paints as review-only metadata
PASS records CMap-encoded Type3 CharProc Image XObject paints as review-only metadata
PASS counts repeated Type3 CharProc Image XObject paints without exposing glyph image payloads
PASS falls back to Type3 font resources when CharProc Resources has trailing operands on current base
PASS rejects duplicate inherited Type3 font resource names before CharProc image review

5 test files, 167 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php
Focused test run: 44 selected test files (root lock skipped)
...
44 test files, 1043 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-type3-font-currentbase.php
exits 0 and emits duplicate_type3_font_names_suppressed=true,
type3_charproc_image_review_suppressed=true,
charproc_payload_visible_text_excluded=true,
executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, page `/Resources null`, indirect null resources, direct/indirect tailed `/Resources`, duplicate `/Resources` keys, duplicate normal Font/Properties entries before ToUnicode/ActualText, duplicate resource category selection, malformed `/Parent` references, generation-exact `/Kids`, category stream rejection, ProcSet review, image XObject inheritance review, annotation appearance inheritance, Form XObject null-resource inheritance, Type3 CharProc metric/text-object boundaries, Type3 CharProc regular image review, page `/Contents` non-inheritance, xref repair, metadata, forms, annotations, or OCR/model handoffs. The bounded behavior is only duplicate inherited Type3 `/Font` resource names before Type3 CharProc image review.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, page-tree resource resolver, resource dictionary parser, duplicate resource-name guard, Type3 font parser, CharProc image review path, stream decoder, and WordPress smoke path. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
