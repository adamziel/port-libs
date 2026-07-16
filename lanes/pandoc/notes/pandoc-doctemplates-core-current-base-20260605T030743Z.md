# Pandoc doctemplates core current-base 2026-06-05T03:07:43Z

## Slice

- Tightened `PortLibs\Pandoc\DocTemplate` loop contexts so each iteration only
  rebinds the requested loop variable path and the anaphoric `it` value.
- Map item fields are no longer copied into the root context, preventing item
  keys such as `title` or `source` from shadowing document-level variables in
  the same loop body.
- Updated the WordPress doctemplate review-packet smoke to iterate warning maps
  with their own `title` fields while keeping the outer review title visible in
  the rendered packet.

## Source Truth

- Pandoc template syntax documents for loops as rebinding the loop variable to
  each array item and also supports `it` as the anaphoric current item:
  https://pandoc.org/demo/example33/6.1-template-syntax.html
- Hackage/Stackage doctemplates documentation records the same variable/loop
  model and the doctemplates package remains Pandoc's template renderer:
  https://hackage.haskell.org/package/doctemplates
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
  roff, Typst, MathJax, KaTeX, online sanitizer, or online service was
  executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 41 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 42 assertions, 1 failure because item `title` and `source`
  fields shadowed the outer `title` and top-level `source`.
- Red-first WordPress smoke after switching the warning loop to direct warning
  maps with item `title` fields:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  failed because the rendered `data-review-title` did not preserve the outer
  review title.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 42 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` breakable-space
markers, parameter-free pipes, enumeration pipes, Unicode display-width
padding, inline partial arrays, resource-map partial discovery, applied partial
rendering, partial final-newline handling, partial recursion guards, alpha
overflow labels, or braced directive tokenizer behavior. It only changes the
loop iteration context so item map fields do not leak into root scope.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and keeps the behavior in the
lane-local loop context. Full doclayout line wrapping for `$~$`,
filesystem-backed template discovery, writer-extension template selection,
default-template parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
