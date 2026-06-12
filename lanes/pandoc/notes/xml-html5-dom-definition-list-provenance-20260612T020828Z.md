# XML/HTML5 DOM Definition List Provenance

Slice: `plib-4fozm`
Base: `b5c2644c3`

## Summary

`XmlHtmlDom` now summarizes HTML definition-list structure for reviewer packets:

- `<dl>` records direct term and definition counts, grouped `dt`/`dd` items, flattened term text, and flattened definition text.
- `<dt>` and `<dd>` child summaries expose `definitionListPart` plus normalized term or definition text.
- Leading orphan `<dd>` groups are preserved as review metadata instead of being dropped.
- Nested `<dl>` elements remain independently summarized while still contributing to the parent description text through the DOM text model.

The WordPress raw HTML handoff fixture verifies deterministic serialization and raw block propagation for definition-list source packets.

## Scope

This is bounded to native PHP XML/HTML5 DOM review metadata. It does not add a browser parser, sanitizer, Pandoc runner, HTML microdata mapping, Markdown/plain/CommonMark behavior, DOCX/EPUB/ODF package behavior, CSL handling, JSON/native constructors, or PDF/Typst behavior.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - Result: `1 test files, 1460 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 71151 assertions, 0 failures`

Status delta after rebase: `phpPass` moves `3210 -> 3211`; added `mappedXmlHtmlDomDefinitionListCases: 1` and `xmlHtmlDomDefinitionListAssertions: 32`.
