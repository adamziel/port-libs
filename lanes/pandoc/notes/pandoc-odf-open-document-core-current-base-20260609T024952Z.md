# pandoc-odf-open-document-core-current-base-20260609T024952Z

## Behavior

Native ODF/OpenDocument parsing now preserves inline `text:template-name` and
`text:line-number` fields as the existing generic `odf-field` review spans.
The handoff carries `text:display` and `style:num-format` metadata into
Markdown and WordPress block attributes and counts the fields in the import
report.

## Evidence

- Base accepted HEAD: `f46ebd3f38d4045b46cad3c6483db1eb4cd9e92b`.
- Red-first check before the source whitelist change:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  failed the new template/line-number fixture with
  `1 test files, 2791 assertions, 1 failures` because the field text was
  dropped.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2809 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  passed after adding template-name and line-number block assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native ODF
XML reader, field metadata mapper, Markdown writer, and WordPress block writer.
No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, TeX/PDF engine,
external converter, online service, live provider test, or live-service
provider test was run.

## Non-Overlap

This slice does not touch previously accepted ODF source metadata, page
variables, chapter/file/statistic fields, line-numbering configuration,
drop-down/input/database fields, subtotal-rule metadata, notes, references,
tracked changes, media, package, table, or style behavior. It only admits two
previously skipped OpenDocument inline field elements into the existing generic
field handoff path.

## Follow-Up

Continue with remaining non-overlapping ODF content gaps such as other inline
field elements, draw object metadata, list/table style edge cases, or package
manifest/media policy.
