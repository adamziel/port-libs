# pandoc-odf-open-document-core-current-base-20260607T142939Z

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-odf-open-document-core-current-base-20260607T142939Z`
- Accepted base: `85b59777f2df68a3c33983f7558f1f3864d76821`
- Source truth: accepted Pandoc lane ODF field-handoff behavior in the current native `OdfReader`, `UPSTREAM_TEST_MANIFEST.json`, and prior ODF notes. The local Pandoc upstream cache was not present in this worktree, so no upstream runner or external converter was used.

## Behavior

- `OdfReader` now preserves editable ODF input fields as reviewer-visible inline field spans:
  - `text:text-input`
  - `text:variable-input`
  - `text:user-field-input`
- Field metadata now includes `text:description` where present.
- User-field declarations are applied to both `text:user-field-get` and `text:user-field-input`.
- Markdown and WordPress handoffs receive the same inert `odf-field` classes and `data-odf-field-*` attributes used by existing ODF field spans.

## Focused Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - New focused case failed before implementation because `text:text-input`, `text:variable-input`, and `text:user-field-input` child text was dropped.
  - Failure line: expected `Inputs Imported packet title, Ready, and Migration Desk remain visible.` but parsed `Inputs , , and  remain visible.`
  - `1 test files, 1515 assertions, 1 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 1538 assertions, 0 failures`
- Final family check: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php`
  - `2 test files, 1633 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  - `odf open document handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/OdfReader.php`
  - `php -l lanes/pandoc/tests/OdfReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`
- JSON metadata validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - passed

Root harness was not run because this is an isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1514` -> `1515`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1934` -> `1935`
- ODF/OpenDocument core cases: `11` -> `12`
- ODF/OpenDocument core focused assertions: `251` -> `275`

## Dependency Closure

No new support component is needed. This slice reuses native `OdfReader` content XML parsing, `AstNode` spans, `MarkdownWriter`, `WordPressBlockWriter`, and the existing WordPress ODF handoff example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF slices for image xlink metadata, text tabs, blockquote styles, heading identifiers/source IDs, conditional/hidden fields, database/page/statistic fields, generated indexes, chart metadata, form controls, object/OLE/math placeholders, frame dimensions, or package URI normalization. It is limited to editable ODF input fields and their downstream Markdown/WordPress handoff metadata.

## Next

For ODF/OpenDocument follow-up, keep work bounded to non-overlapping native content/styles/meta XML mapping such as remaining table/list style handoffs, draw/frame metadata not already covered, or additional field forms that preserve visible reviewer text.
