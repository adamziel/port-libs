# markerPDF Inline Image Tokenizer Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T031954Z`

Base accepted HEAD: `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable PDF text extraction to parser layers before OCR/model stages. At this native no-GPU boundary, inline images are raster payloads between `BI`/`ID`/`EI` content-stream operators and must not be tokenized as visible text, while ordinary PDF content after the real inline-image terminator must still flow into WordPress paragraphs.

The PDF content-stream boundary allows images inside graphics-state, marked-content, and compatibility scopes opened before `BI`. Their close operators (`Q`, `EMC`, `EX`) can appear immediately after the real `EI`, before the next visible `BT ... ET` text object. The tokenizer must therefore recognize those close operators as valid post-image content when deciding whether an earlier candidate `EI` is the real image terminator or whether to keep scanning until a later stray `EI`.

## Change

- `PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now accepts external `Q`, `EMC`, and `EX` scope close operators when validating content between a preview-only inline-image fallback `EI` and a later stray `EI`.
- The same validator now permits those close operators when they follow the candidate `EI` on the same content line.
- `PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` adds focused marked-content, graphics-state, and compatibility-scope cases where visible text after the scope close used to be swallowed until a later stray `EI`.
- `wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` surfaces matching WordPress smoke flags:
  - `preview_only_outer_marked_content_close_preserves_following_text`
  - `preview_only_outer_graphics_state_close_preserves_following_text`
  - `preview_only_outer_compatibility_close_preserves_following_text`

## Red-First Evidence

Before the source edit, local probes for the marked-content and graphics-state forms produced only:

```text
Before Outer Marked
Visible After Outer Marked Stray
```

and:

```text
Before Outer Graphics
Visible After Outer Graphics Stray
```

The text immediately after `EMC` and `Q` was swallowed because the validator rejected those external close operators and chose the later stray `EI` as the terminator.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

Result: `1 test files / 432 assertions / 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImage*Test.php lanes/markerpdf/tests/PdfParserInlineStream*Test.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php
```

Result: `9 test files / 1174 assertions / 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

Result: smoke emitted the three new `preview_only_outer_*_close_preserves_following_text=true` flags, with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline-image tight `ID`, tight `EI`, NUL whitespace, comment-after-`ID`, slash-delimited `EI`, DCT/JPX/CCITT/JBIG2 preview filter, unsupported filter, ActualText, same-line text, path/color/text-state, XObject `Do`, or full in-segment graphics/compatibility wrapper cases. The bounded behavior is only close operators for scopes opened before the inline image.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, preview-only image filter handling, marked-content/graphics-state/compatibility operator validation, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, raster rendering, and exact upstream model benchmark parity remain intentionally out of scope under the markerPDF no-GPU directive.
