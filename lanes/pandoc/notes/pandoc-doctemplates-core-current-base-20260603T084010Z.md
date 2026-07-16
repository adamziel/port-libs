# Pandoc doctemplates core current-base 2026-06-03T08:40:10Z

## Slice

- Extended `PortLibs\Pandoc\DocTemplate` with Pandoc doctemplate multiline nesting.
- Added explicit `$^$` handling: the directive records the current rendered column and indents subsequent lines in the next rendered chunk to that column.
- Added automatic nesting when a multiline variable appears alone on an indented template line.
- Updated the WordPress review-packet example so multiline block-comment body output remains nested inside wrapper markup without caller-side preformatting.

## Source Truth

- Pandoc User's Guide `Template syntax`, `Nesting`: `$^$` aligns multiline content to the directive column, and standalone indented multiline variables are nested automatically.
- No Pandoc binary, Haskell test binary, external template engine, online service, Word, LibreOffice, or TeX/PDF engine was executed.

## Evidence

- Red-first check before implementation: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed on unsupported `$^$` and missing automatic indentation for standalone multiline variables.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed: 1 test file, 12 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 4 test files, 2,478 assertions, 0 failures.
- `php -l lanes/pandoc/src/DocTemplate.php` passed.
- `php -l lanes/pandoc/tests/DocTemplateTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` passed.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No new external support component is needed. This is native PHP doctemplate evaluator behavior on top of the existing lane bootstrap and test runner. It does not activate DOCX/OpenXML parsing, legacy DOC/CFB, PDF, EPUB/ODT, citation/CSL, YAML parsing beyond the accepted reader slice, TeX/PDF engines, Haskell binaries, online services, or external template engines.

## Follow-Up

Keep doctemplate partial inclusion and predefined pipes such as `pairs`, `uppercase`, `length`, `first`, and `last` as separate bounded slices. The broader lane can now use multiline-safe doctemplate rendering when wiring ZIP/OPC/YAML support into a minimal DOCX document-part import path.
