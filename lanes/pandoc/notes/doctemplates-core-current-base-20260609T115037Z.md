# Pandoc Doctemplates Core Slice 2026-06-09

## Behavior

- Added `PandocTemplate`, a bounded native PHP renderer for the core
  `jgm/doctemplates` behavior Pandoc uses for writer templates.
- Covered `$...$` and `${...}` delimiters, literal `$$`, `$--` comments,
  variable interpolation, booleans, list/map/scalar rendering, conditionals
  with `else` and `elseif`, multiline newline swallowing, `for` loops, `$sep$`,
  literal `[separator]` interpolation, `it`, nested path rebinding, and one
  final newline trimmed from interpolated values.
- Added scalar/list pipes used by the sampled core fixtures:
  `uppercase`, `lowercase`, `length`, `pairs`, `first`, `last`, `rest`,
  `allbutlast`, `reverse`, `chomp`, `alpha`, and `roman`.
- Added a WordPress review-handoff example that templates front matter,
  conditional paragraph markup, an unescaped source URL, and repeated import
  checklist rows without shelling out to Pandoc or another template engine.

## Source Truth

- Primary upstream source: `jgm/doctemplates` shallow clone at `/tmp` during
  this worker run, especially `README.md`, `src/Text/DocTemplates.hs`,
  `src/Text/DocTemplates/Internal.hs`, `src/Text/DocTemplates/Parser.hs`, and
  these core fixture boundaries: `basic.test`, `basic-with-braces.test`,
  `basic-with-it.test`, `boolean.test`, `conditionals.test`, `elseif.test`,
  `forloop.test`, `values.test`, `comments.test`, `empty.test`,
  `final-newline.test`, `loop-in-object.test`, `nested-loop.test`, and the
  bounded literal separator/scalar pipe portions of `pipes.test`.
- Hackage doctemplates 0.11.0.1 README was used as confirming source for the
  public template contract.

## Evidence

- `php -l lanes/pandoc/src/PandocTemplate.php` passed.
- `php -l lanes/pandoc/tests/PandocTemplateTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-handoff.php`
  passed.
- `php tools/run-tests.php lanes/pandoc/tests/PandocTemplateTest.php` passed:
  1 test file, 16 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 2 test files, 2,331
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-handoff.php
  --self-test` passed.

## Dependency Closure

This activates and satisfies the base lane-local `pandoc-doctemplates-core`
support component for bounded writer-template rendering. No new support
component is needed beyond that existing backlog row. The slice does not
activate DOCX/OpenXML, PDF, EPUB/ODT, YAML/JSON metadata, archive/compression,
Unicode/charset, citation, math, syntax-highlighting, or table-geometry rows.

## Blocker / Next

- Blocker: no local PHP blocker. Full upstream Pandoc and doctemplates Haskell
  runners were not executed in this isolated micro-slice.
- Next bounded doctemplates work, if needed by a writer-template slice:
  partial resolution with extension substitution and loop guard, `^` nesting,
  breakable-space reflow/nowrap semantics, or multiline doclayout behavior for
  `left`, `right`, and `center` block alignment pipes.
