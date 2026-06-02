# font-simple-type3-cmap-spacing-currentbase

## Behavior

Type3 fonts with an object-valued `/Encoding` CMap now treat mapped CID 32 as a source word-spacing boundary when applying current text-state `Tw` advance. This keeps same-line `Tm` grouping consistent for both `Tj` and `TJ` text showing when the source byte is not raw `0x20` but the Encoding CMap maps it to the simple-font space code.

## Source truth

- Upstream markerPDF extraction is a native PDF text pipeline backed by `pdftext`/PDF parser semantics; the lane manifest tracks that architecture plus CMap, ToUnicode, font-width, and text-showing operator source-space boundaries.
- PDF text state word spacing applies to the font character code space, while Type3 `/Encoding` can be supplied as a CMap in this port. Existing accepted slices already map Type3 Encoding CMap CIDs and Type3 ToUnicode source codes; this slice closes the remaining spacing edge for CID 32.

## Evidence

Red-first focused test on accepted base `99591cbc6337f72bc79127211674461d42c783cc`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php
FAIL uses Type3 Encoding CMap CID 32 as source word spacing before WordPress grouping on current base
Actual: array (
  0 => 'A\u2060B C',
  1 => 'D\u2060E F',
)
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php
PASS uses Type3 Encoding CMap CID 32 as source word spacing before WordPress grouping on current base
1 test files, 9 assertions, 0 failures
```

## Non-overlap

This does not repeat the accepted text-operator source-space word-spacing slice for decoded bidi replacement strings, the accepted Type3 CIDSet CMap width grouping, or the accepted Type3 CharProc glyph-name ToUnicode recovery. The new edge is specifically Type3 Encoding CMap CID 32 feeding text-state word spacing before positioned WordPress paragraph grouping.

## Dependency closure

No new support component is needed. The patch reuses the existing `PdfTextExtractor` CMap parser, Type3 font-map construction, glyph-width source segmentation, and text-state advance code; Python, pdftext, pypdfium, model workers, and external PDF tools remain excluded.
