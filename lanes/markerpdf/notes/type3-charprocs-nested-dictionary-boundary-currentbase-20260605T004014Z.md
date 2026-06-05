# markerPDF Type3 CharProcs nested dictionary boundary current base

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260605T004014Z`

Base accepted HEAD: `7dc69d5aea3948399682b3467340c79f130a10f6`

## Source Truth

The local markerPDF upstream clone is not available in this isolated worktree,
so this slice uses the pinned markerPDF manifest, accepted Type3 parser notes,
and PDF Type3 font semantics as source truth. In the no-GPU searchable-PDF
path, markerPDF's upstream pdftext/PDFium boundary treats Type3 `/CharProcs`
as a font glyph-program dictionary. Only top-level entries in that dictionary
name glyph programs; nested private dictionaries, strings, and comments inside
the dictionary are not glyph references and must not drive WordPress text
advance grouping or fallback stream exclusion.

## Red Check

Before the parser change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (0 => 'WideBlock', 1 => 'Thin Text')
Actual:   array (0 => 'Wide Block', 1 => 'Thin Text')
```

That proved the broad `/Name obj gen R` scan inside the `/CharProcs`
dictionary accepted a nested `/Private << /W.wide 5 0 R ... >>` decoy and
overrode the real top-level wide glyph program.

## Implementation

`PdfTextExtractor::charProcObjectReferences()` now walks the resolved
`/CharProcs` dictionary as top-level PDF name/value pairs. It skips comments,
literal strings, hex strings, arrays, and nested dictionaries through the
existing PDF value skipper before accepting `<name> <object> <generation> R`
references. Exact generation handling and indirect `/CharProcs` dictionary
resolution remain unchanged.

The focused fixture proves:

- real top-level Type3 CharProc entries keep `WideBlock` joined;
- thin `d1` glyph widths still preserve `Thin Text`;
- nested dictionary, literal-string, and comment decoys do not supply glyph
  references;
- CharProc payload text stays out of visible WordPress paragraphs.

## Evidence

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php
```

Result: `1 test files, 8 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result: `12 test files, 721 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-nested-dictionary-boundary-currentbase.php
```

Result: emitted Gutenberg paragraphs for `WideBlock` and `Thin Text`, with
`top_level_charproc_references_preserved=true`,
`nested_dictionary_references_excluded=true`,
`literal_and_comment_references_excluded=true`,
`charproc_payload_visible_text_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Additional local checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-nested-dictionary-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
git diff --check -- lanes/markerpdf
```

Result: all passed; `git diff --check -- lanes/markerpdf` produced no output.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner,
exact-generation object lookup, top-level dictionary scanner, Type3 CharProc
width parser, stream decoder, and text-advance grouping path. No Python,
PDFium, pypdfium2, Surya, Texify, Torch, OCR, GPU/model execution, browser
service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted direct Type3 `d0`/`d1` width handling, direct
CharProc fallback exclusion, same-number CharProc stream generation selection,
indirect CharProcs dictionary exact-generation selection, top-level font
`/CharProcs` lookup, Type3 Encoding Differences, named/base Encoding color
glyph widths, Type3 CMap/CIDSet grouping, Type3 glyph-name Unicode recovery,
Type0 CID widths, or xref/object-stream repair. The new boundary is
specifically token-aware top-level entry parsing inside the resolved Type3
`/CharProcs` dictionary.
