# Legacy DOC/CFB Embedded Object Reference Slice

Micro-slice: `pandoc-legacy-doc-cfb-core-current-base-20260606T052708Z`
Base: `325c2b0f457f1a0f74e1d2a0b7da113bbb15e2f6`

## Source Truth

- MS-DOC main-document text can contain the `0x01` object replacement character where a legacy embedded object is anchored.
- Embedded OLE payloads live under CFB `ObjectPool` storages; the lane already extracts bounded metadata from `ObjInfo`, `CompObj`, `Ole10Native`, and presentation streams without exposing native bytes.
- For WordPress/Pandoc-like handoff, the conversion contract is to preserve the inline review anchor and safe ObjectPool provenance while keeping OLE native and presentation payload bytes non-exposable.

## Patch

- `LegacyDocReader` now builds `embeddedObjectReferences` from main-document `0x01` placeholders and ordered ObjectPool reports.
- The document attrs and metadata include `embeddedObjectReferenceCount` plus safe reference metadata: CP, object index, storage path, object id, label, native-data byte count, transmission format, and non-exposure policy.
- Inline AST generation replaces the raw `0x01` placeholder with a `legacy-doc-object-ref` span for Markdown and WordPress writers.
- The WordPress legacy DOC handoff example now includes an embedded-object placeholder and self-tests the safe review span while checking that OLE payload bytes and the raw control character are not rendered.

## Verification

- Initial focused check after wiring: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` failed with `1 test files, 728 assertions, 1 failures` because the WordPress span assertion did not include all safe object metadata attributes.
- Focused tests after patch: `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php` passed with `1 test files, 746 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test` passed with `legacy doc handoff self-test ok`.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is required. This reused native PHP `LegacyDocReader`, `CompoundFileBinary`, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and the existing ObjectPool/OLE metadata parser. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, or live provider test was executed.

## Non-Overlap

This slice owns only inline main-document embedded-object placeholder handoff for already-discovered ObjectPool storages. It avoids accepted legacy DOC/CFB work for CFB validation, directory timestamp/CLSID/state-bit provenance, encrypted FIB and `lKey` preflight, `fExtChar` direct Unicode text, FibRgLw97 subdocument trimming, CLX PCD flag validation, OLE property sets, ObjectPool inventory-only reports, macros, SttbfAssoc metadata, Plcfld field ranges, styles, sections, notes, bookmarks, lists, and formatting runs. Follow-up should keep inline picture extraction policy, FFData option decoding, header/footer/textbox field tables, and any true embedded-package byte export policy as separate bounded slices.
