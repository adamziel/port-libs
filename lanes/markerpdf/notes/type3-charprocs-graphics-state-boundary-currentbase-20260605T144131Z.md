# markerPDF Type3 CharProcs graphics-state boundary current base

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T144131Z`
Session: `port-dev-markerpdf-type3-charprocs-20260605T144131Z`
Base accepted HEAD: `9bea7b4c06e1f594835627b0cfa11df5c9346166`

## Source truth

Pinned upstream markerPDF routes searchable PDF text through PDF text/font
extraction before Markdown assembly. Under the current no-GPU lane scope, the
native PHP fallback owns the Type3 font boundary: `/CharProcs` glyph programs
may declare widths with `d0`/`d1`, but malformed pre-metric graphics-state
operators must not let a decoy width override fallback font metrics before
WordPress paragraph grouping.

## Behavior

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now tracks the CharProc
pre-metric graphics-state save depth. Balanced saved-state setup remains valid,
including color glyph programs that use `q ... d0/d1 ... Q`. An unmatched `Q`
before the metric operator now fails the CharProc width closed, so the font
falls back to descriptor/default width metadata instead of joining words with a
decoy glyph width.

The focused fixture covers:

- balanced `q ... Q` setup before `d0`, preserving `GoodPath`;
- unmatched `Q` before `d0`, rejecting the decoy width and preserving
  `Rest Gap`;
- saved graphics-state color-style setup around `d1`, preserving `SaveGap`;
- CharProc payload text exclusion from visible WordPress paragraphs.

## Red-first evidence

Before the source edit, the new focused probe failed because malformed
pre-metric graphics-state restores were still allowed to supply decoy widths:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects unbalanced Type3 CharProc graphics state before WordPress text grouping on current base
Actual text lines included the malformed joined RestGap output.
1 test files, 1 assertions, 1 failures
```

The final fixture preserves saved-state `SaveGap` as valid Type3 color-glyph
style setup and only rejects the unmatched-restore `RestGap` boundary. The
focused and adjacent checks pass.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects unbalanced Type3 CharProc graphics state before WordPress text grouping on current base
1 test files, 13 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsGraphicsStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS rejects unbalanced Type3 CharProc graphics state before WordPress text grouping on current base
PASS uses named Type3 color glyph CharProc widths while excluding resource payload text on current base
2 test files, 22 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
Focused test run: 36 selected test files (root lock skipped)
36 test files, 315 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-graphics-state-boundary-currentbase.php
```

The smoke emits `balanced_graphics_state_width_preserved=true`,
`unmatched_restore_width_rejected=true`,
`saved_graphics_state_width_preserved=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, CharProc
FontMatrix normalization, color glyph resource payload exclusion, path/text-state
setup, inline-image rejection, marked-content wrappers, operand-count guards,
CharProcs dictionary/generation selection, fallback stream exclusion,
Type3 CMap/CIDSet grouping, simple-font width clipping, or broader CMap/font
source-width behavior. The bounded behavior is only unmatched pre-metric `Q`
handling in Type3 CharProc width parsing.

An exploratory broad bundle that also included `PdfTextExtractorTest.php`
surfaced two unrelated accepted-base CMap failures in that file. The Type3-only
family above is green and is the verification gate for this patch.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object
scanner, stream decoder, content tokenizer, Type3 CharProc width parser,
font descriptor fallback metrics, and WordPress smoke path. GPU/OCR/model
execution, raster rendering, and exact upstream model benchmark parity remain
intentionally out of scope under the current no-GPU markerPDF directive.
