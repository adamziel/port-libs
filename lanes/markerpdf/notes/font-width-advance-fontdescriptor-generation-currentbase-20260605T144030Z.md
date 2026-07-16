# markerPDF Font Width Advance FontDescriptor Generation Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T144030Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T144030Z`

Base accepted HEAD: `9bea7b4c06e1f594835627b0cfa11df5c9346166`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable PDF glyph extraction and
geometry to parser-backed pdftext dictionaries before Marker block/span
assembly. Under the no-GPU markerPDF scope, the native PHP fallback owns the
low-level PDF font dictionary boundary before WordPress paragraph grouping.

PDF indirect references include both object number and generation. A simple
font that references `/FontDescriptor 7 0 R` must read generation 0 descriptor
fields for `/MissingWidth`, `/FontName`, and `/Flags`, even when a later
generation 1 object with the same object number is present elsewhere in the
file.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py

## Native Behavior Added

`PdfTextExtractor::fontDescriptorBody()` now resolves `/FontDescriptor` through
`objectBodyForExactReference()` instead of using only the referenced object
number. This keeps exact-generation descriptor dictionaries authoritative for
font advance fallback and styled span metadata.

The focused fixture uses a Type1 font without explicit `/Widths`, with
`/FontDescriptor 7 0 R` declaring `/MissingWidth 250`, while an unreferenced
`7 1 obj` descriptor declares `/MissingWidth 1000`. Before the source change,
the native path used the later generation, joined `ABCD`, and emitted
`UnreferencedDescriptor_non_symbolic`. After the change, the referenced
generation 0 descriptor preserves the real `AB CD` positioned gap, 250-unit
MissingWidth bboxes, and `ReferencedDescriptor_symbolic` span fonts.

## Evidence

Red-first current-base probe before the source edit:

```text
extractTextLines => ['ABCD']
styled span font => UnreferencedDescriptor_non_symbolic
styled bboxes => [[0.0, 0.0, 24.0, 12.0], [24.0, 0.0, 48.0, 12.0]]
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves exact-generation FontDescriptor MissingWidth before current advance gaps
1 test files, 380 assertions, 0 failures
```

Descriptor-adjacent focused files:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 30 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `exact_generation_fontdescriptor_missingwidth_gap_preserved=true`,
`exact_generation_fontdescriptor_bboxes_preserved=true`,
`exact_generation_fontdescriptor_font_preserved=true`,
`exact_generation_fontdescriptor_unreferenced_font_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `2002 -> 2003`.
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `31 PASS / 366 assertions -> 32 PASS / 380 assertions`.
- Focused new assertions: `14`.
- WordPress scenarios: `1735 -> 1736`.
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`.

## Exclusion

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` was
not used as acceptance evidence because the current worktree still reports two
unrelated ToUnicode `usecmap` failures:
`inherits ToUnicode usecmap mappings before WordPress text extraction` and
`guards cyclic ToUnicode usecmap inheritance and codespace counts before
WordPress text extraction`. The descriptor-specific adjacent files listed
above pass.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, exact-generation object resolver, FontDescriptor metadata path,
MissingWidth text-advance estimator, styled-span bbox path, and WordPress smoke
renderer. OCR, Surya, Texify, Torch, GPU/model execution, Streamlit/FastAPI
model workers, external PDF tools, and exact upstream model benchmark parity
remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted exact-generation simple-font `/Widths` arrays,
indirect FontDescriptor field values, Type3 FontMatrix MissingWidth
normalization, simple-font average-width fallback, quote/relative/absolute
positioning, terminal character spacing, text matrix/rise handling, `TJ`
backtracking, vertical `/W2`, indirect CID `/W`/`W2`, negative first CID array
rejection, non-finite width rejection, CMap source-width fallback, xref repair,
stream filters, annotations, forms, images, or supplied table/equation
handoffs. The bounded behavior is only exact-generation `/FontDescriptor`
dictionary resolution before native font advance and styled-span review.
