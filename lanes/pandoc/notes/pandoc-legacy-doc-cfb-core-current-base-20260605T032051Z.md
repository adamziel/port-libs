# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T032051Z`
Base accepted HEAD: `82ff7b09c5e0123addfe77de2dff76eaf17d3465`

## Behavior Added

- Added bounded ObjectPool embedded OLE object preflight to `LegacyDocReader`.
- The reader now groups CFB streams under `ObjectPool/<object-storage>` and
  reports each embedded object with:
  - storage path and object id;
  - stream count and total byte size;
  - stream roles for `\001CompObj`, `\001Ole10Native`, `\002OlePres*`,
    `\003ObjInfo`, `\003EPRINT`, and private streams;
  - `\003ObjInfo` ODT transmission format codes for text, Unicode text, RTF,
    HTML, bitmap, DIB, and metafile cases;
  - `canExposeBytes=false` so native and presentation payload bytes remain
    reviewer follow-up data, not rendered WordPress content.
- The document AST and the `readBytes()` result now expose
  `embeddedObjects`, while metadata exposes `embeddedObjectCount`.
- Expanded the focused CFB fixture builder to write chained directory sectors,
  which is needed for realistic nested ObjectPool storages with several
  streams.
- Updated the WordPress legacy DOC handoff example self-test so review packets
  include ObjectPool preflight without leaking embedded payload bytes.

## Source Truth

- Microsoft MS-DOC ObjectPool Storage documents that the ObjectPool storage
  contains storages for embedded OLE objects:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/f7983581-d107-4a1f-b5f7-f3650e777c04`
- Microsoft MS-DOC ObjInfo Stream documents `\003ObjInfo` inside each
  ObjectPool storage and its ODT metadata role:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/13ba10a8-d8b2-433b-bf3b-ec238dc8f9ce`
- Microsoft MS-OLEDS Embedded Objects documents OLE2 `\001Ole10Native` native
  data streams and `\002OlePres*` presentation streams:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/2677fcf2-ad48-4386-ba8f-b1b7baf4c02f`
- Microsoft MS-OLEDS CompObjStream documents the `\001CompObj` stream role:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-oleds/142e0420-2f74-4ed9-829b-0b3d5a684d01`

This slice intentionally reports embedded object metadata and byte sizes only.
It does not parse full OLENativeStream payloads, expose embedded bytes, parse
CompObj display names, extract images, run Word or LibreOffice, decrypt
documents, evaluate fields, or run the upstream Haskell runner.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 126 assertions, 0 failures`
- During fixture expansion:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Initial result exposed a local fixture-builder limit:
    `CFB directory tree points outside the directory`
  - Fixed by chaining directory sectors in the focused CFB fixture builder.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 145 assertions, 0 failures`
  - PASS lines: `23`
  - Delta: `+1` PASS line / `+19` assertions over the prior focused legacy
    DOC/CFB test run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6343 assertions, 0 failures`
  - `rg -c '^PASS'` over the captured output reported `574`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate rendering, ZIP/OPC, archive compression,
DOCX/ODT, table geometry, math/TeX, charset/Unicode, XML/HTML5 DOM,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB storage hierarchy
traversal, CFB directory ordering/red-black validation, standard and custom
OLE property metadata, Word FIB encrypted-stream preflight, fExtChar Unicode
direct text-range decoding, CLX text extraction, CLX PCD flag validation, or
field-code result handoff. It owns only bounded ObjectPool embedded OLE object
preflight after the legacy DOC CFB has already been parsed.

## Follow-Up

Keep full OLENativeStream parsing, CompObj display-name parsing, embedded
object extraction/export policy, Plcfld PLC/Fld table loading, nested fields,
legacy DOC style/list tables, footnote/endnote PLCs, revision-mark format
property inspection, macro stream policy, image extraction, encrypted DOC
password/decryption policy, and full upstream Pandoc runner parity as separate
bounded slices.
