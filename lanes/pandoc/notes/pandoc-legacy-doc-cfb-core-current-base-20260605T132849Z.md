# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T132849Z`

Base accepted HEAD: `e65ebdc1e28b85d0eb56ff5b67d795e6ccf441f5`

Date: 2026-06-05 UTC

## Behavior

`LegacyDocReader` now performs bounded native extraction of legacy Word DOC
list-table metadata from the table stream:

- reads `FibRgFcLcb97.fcPlfLst/lcbPlfLst` and
  `FibRgFcLcb97.fcPlfLfo/lcbPlfLfo`;
- parses `PlfLst` `LSTF` records plus the appended `LVL` records whose byte
  length is not included in `lcbPlfLst`;
- reports list identifiers, template codes, linked style ISTDs, simple/hybrid
  flags, level number formats, placeholder templates such as `%1.`, follow
  character behavior, and bounded property-group byte counts;
- parses `PlfLfo` override records and `LFOData`/`LFOLVL` start-at overrides;
- exposes the data as review-only `listFormats` and `listOverrides` metadata
  on the return array, AST attributes, and WordPress handoff example.

The reader still sets `canApplyNumbering=false`; this slice does not convert
paragraphs into numbered/bulleted AST lists.

Malformed table guards reject invalid `PlfLst` lengths, duplicate LSTF list
identifiers, unknown override list identifiers, invalid/reserved level fields,
and bad placeholder offsets before numbering metadata is exposed.

## Source Truth

MS-DOC defines `fcPlfLst` as the table-stream offset for `PlfLst`; `lcbPlfLst`
covers the `PlfLst` header and `LSTF` array, while the corresponding `LVL`
array is appended immediately after it. Simple lists have one appended `LVL`;
multi-level lists have nine. `PlfLfo` then supplies list-format overrides and
per-list `LFOData`/`LFOLVL` override records. This slice ports that bounded
format contract only.

## Verification Evidence

Focused legacy DOC verification:

`php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`

Result: `1 test files, 550 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`

Result: `legacy doc handoff self-test ok`.

Syntax and JSON checks:

- `php -l lanes/pandoc/src/LegacyDocReader.php` passed.
- `php -l lanes/pandoc/tests/LegacyDocReaderTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php` passed.
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'` passed.

Final whitespace check:

`git diff --check -- lanes/pandoc`

Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- Manifest mapped checks: `1379 -> 1380`.
- Lane PHP pass count: `922 -> 924`.
- Focused legacy DOC test coverage: `502 -> 550` assertions.
- Added one mapped native legacy DOC/CFB list-table handoff case and two
  focused PASS cases.

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`CompoundFileBinary`, `LegacyDocReader`, Pandoc-like AST, and
`WordPressBlockWriter` paths. It does not invoke Pandoc, Cabal, Haskell test
binaries, Word, LibreOffice, `zip`, `unzip`, external template engines,
TeX/PDF engines, browser renderers, roff, Typst, JavaScript, online sanitizers,
or online services.

## Non-Overlap

This slice does not repeat accepted legacy DOC work for CFB header parsing,
MiniFAT/FAT chain traversal, FAT/DIFAT sector identity preflight, directory
timestamps/CLSID/state-bit provenance, OLE property metadata, encrypted FIB
rejection, fExtChar Unicode text ranges, CLX main-text extraction, CLX PCD flag
validation, FibRgLw97 subdocument text boundaries, bookmarks, note/comment
PLCs, section/style/formatting tables, field-code result handoff, ObjectPool
embedded object inventory, macro-project preflight, DOCX, ODT, EPUB3, ZIP/OPC,
XML/HTML5 DOM, or table geometry.

The owned behavior is only legacy Word DOC list-format and list-override table
metadata extraction for review handoff.

## Follow-Up

Keep paragraph-to-list application, list restart expansion, paragraph/character
SPRM interpretation for list levels, textbox/header/footer subdocument routing,
encrypted DOC password/decryption policy, embedded object export policy, and
full upstream Pandoc runner parity as separate bounded slices.
