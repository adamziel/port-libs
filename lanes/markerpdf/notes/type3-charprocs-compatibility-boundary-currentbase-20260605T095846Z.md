# markerPDF Type3 CharProcs compatibility boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T095846Z`

Base accepted HEAD: `ea6c1e547df97830b0857c42be5a1b64c0335a43`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium
before OCR/model handoff. At this native parser boundary, Type3 `/CharProcs`
are glyph programs and `d0`/`d1` provide glyph metrics before text advance
grouping. PDF content streams also support `BX`/`EX` compatibility sections,
where unknown compatibility operators must not become visible text or block
the following valid glyph metric.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'ThinText')
```

That proved `PdfTextExtractor` rejected Type3 CharProc metrics after a
`BX ... EX` compatibility section and fell back to stale `/Widths` values.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now tracks Type3
CharProc compatibility sections. It ignores operands and operators between
balanced `BX` and `EX` wrappers before looking for `d0`/`d1`, so unknown
compatibility operators do not reject a later valid metric. Unclosed
compatibility sections still fail closed because no metric outside the section
is accepted.

The focused fixture proves:

- `BX ... EX` before `d0` preserves a wide CharProc metric and keeps
  `WideBlock` joined despite stale narrow `/Widths`;
- `BX ... EX` before `d1` preserves a thin CharProc metric and keeps
  `Thin Text` separated despite stale wide `/Widths`;
- unknown compatibility operator names remain non-visible and do not become
  metric operands;
- Type3 CharProc payload text remains excluded from WordPress paragraphs.

## Evidence

Focused red-first/green command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 10 assertions, 0 failures`.

Scoped Type3/font family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcs*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php
```

Result: `26 test files, 220 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-compatibility-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`compatibility_section_widths_preserved=true`,
`wide_block_spacing_preserved=true`, `thin_text_spacing_preserved=true`,
`compatibility_operator_payload_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsCompatibilityBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-compatibility-boundary-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, stream decoder, content tokenizer, Type3 CharProc width parser,
font-width grouping path, focused PHP tests, and WordPress smoke renderer. No
Python, PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution,
browser service, live provider, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling,
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, comment-split
CharProc references, top-level `/CharProcs` lookup, nested CharProcs dictionary
parsing, Type3 Encoding Differences, private-glyph fallback, named/base
Encoding color glyph widths, Type3 CMap/CIDSet grouping, Type3 FontMatrix
normalization, `wx/wy` vector transformation, marked-content wrappers,
path-setup wrappers, inline-image paint rejection, image/subtype CharProc
boundaries, pre-metric painting rejection, or xref/object-stream repair. The
new boundary is only balanced Type3 CharProc `BX`/`EX` compatibility sections
before `d0`/`d1` metrics.

## Exclusion

An exploratory broader run that also included `PdfTextExtractorTest.php`
reported two unrelated current-base stream-filter failures:
`recovers stale stream Length with bounded endstream terminators...` and
`fails closed on unsupported or corrupt stream filters...`. Those failures are
outside this Type3 CharProc slice, were not caused by this patch, and were not
used as the final focused gate.
