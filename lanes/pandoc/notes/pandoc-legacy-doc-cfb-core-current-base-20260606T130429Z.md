# Pandoc Legacy DOC CFB Core Current Base - 2026-06-06T13:04:29Z

## Scope

- Base accepted HEAD: `d7dd35e193e433506c4031446b30b2cc5f04e717`.
- Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T130429Z`.
- Implemented a bounded legacy Word DOC/CFB inline picture placeholder policy for FIB `fHasPic` documents.
- Preserves U+0001 inline picture placeholders as metadata-only review spans in the Pandoc-like AST, Markdown, and WordPress block handoff.
- Does not expose image bytes, decode PICF/OfficeArt/BLIP records, or call Pandoc, Word, LibreOffice, zip/unzip, TeX/PDF engines, browser renderers, online services, or Haskell test binaries.

## Behavior

- `LegacyDocReader` now reports `pictureReferences` when FIB `hasPictures` is true and inline U+0001 placeholders remain after ObjectPool reference matching.
- Each reference records the source character position, picture index, placeholder character code, extraction policy, and byte-exposure capability.
- Document metadata now includes `pictureReferenceCount`, `pictureReferences`, and `pictureExtractionPolicy`.
- Inline output emits safe spans with `legacy-doc-picture-ref` class and data attributes for downstream WordPress/Pandoc review.
- ObjectPool references keep priority; when an ObjectPool entry consumes a U+0001 marker, the remaining unmatched picture placeholders become picture references.

## Verification

- Red-first focused check before source behavior:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  Result: `1 test files, 822 assertions, 1 failures`.
- Green focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  Result: `1 test files, 848 assertions, 0 failures`.
- Example smoke after implementation:
  `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  Result: `legacy doc handoff self-test ok`.

## Dependency Closure

- No new support component is required for this slice.
- Reused native `CompoundFileBinary`, `LegacyDocReader`, `AstNode`, `MarkdownWriter`, and `WordPressBlockWriter` handoff behavior.
- Remaining dependency gates are actual legacy DOC picture byte extraction/export policy, OfficeArt/BLIP anchoring, encrypted DOC decryption, and full upstream Word binary runner parity.

## Non-Overlap

- This slice avoids prior legacy DOC work on FIB parsing, piece-table decoding, bookmarks, notes, fields, custom properties, ObjectPool OLE placeholders, FFData form-field metadata, encrypted-document preflight, fast-saved piece-table split diagnostics, and CFB mini-stream sector-chain behavior.
- It intentionally maps only the current-base inline picture placeholder handoff policy.
