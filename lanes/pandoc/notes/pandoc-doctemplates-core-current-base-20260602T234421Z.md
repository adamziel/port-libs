# Pandoc doctemplates core current-base 2026-06-02T23:44:21Z

## Slice

- Added `PortLibs\Pandoc\DocTemplate`, a bounded native PHP renderer for the core Pandoc doctemplate syntax needed by standalone conversion templates.
- Covered comments, matched `$...$` and `${...}` delimiters, literal `$$` dollars, whitespace-trimmed directives, dotted variables, literal list separators such as `${keywords[, ]}`, map/list/scalar rendering, conditionals with `elseif` and `else`, Pandoc-style truthiness, `for` loops over arrays/maps/scalars, `$sep$` separators, anaphoric `$it$`, nested loop/conditional evaluation, and unclosed-control diagnostics.
- Added `wordpress-doctemplate-review-packet.php` as the WordPress-visible path: a review wrapper template renders author lists, warning loops, and already-escaped block body HTML without shelling out to Pandoc or another template engine.

## Source Truth

- Pandoc User's Guide `Template syntax`: comments, delimiters, literal dollar escaping, interpolated variables, conditionals, `elseif`, for loops, `sep`, and `it`.
- Existing lane manifest remains static inventory evidence; the upstream checkout is not hydrated in this worker, and no Haskell runner or external template engine was executed.

## Evidence

- `php -l lanes/pandoc/src/DocTemplate.php`
- `php -l lanes/pandoc/tests/DocTemplateTest.php`
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed: 1 test file, 10 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 2 test files, 2,327 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed.
- `git diff --check -- lanes/pandoc` passed.

## Dependency Closure

No external support component is needed. This slice adds the needed native PHP doctemplate support component directly under `lanes/pandoc/src` and reuses the existing lane test runner and example pattern. It does not activate DOCX/OpenXML, ZIP/OPC, PDF, EPUB/ODT, citation/CSL, YAML metadata, TeX/PDF engines, Haskell binaries, online services, or external template engines.

## Follow-Up

Next bounded doctemplate candidates: partial inclusion and variable-applied partials, predefined pipes such as `pairs`, `uppercase`, `length`, `first`, and `last`, or the `^` multiline nesting directive. Those should be separate slices with focused tests because they materially widen the template evaluator.
