# Pandoc doctemplates core current-base 2026-06-05T09:26:27Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` applied-partial context rebinding with
  upstream doctemplates.
- Applied partials such as `${ articles:card() }` now render each partial with
  both `it` and the applied variable path rebound to the current item.
- The same rebinding applies after variable pipes and for nested paths, so
  `${ warnings/rest/first:summary() }` exposes `warnings.source` and `it.source`
  consistently, and `${ import.items/last:row() }` exposes `import.items.title`.
- Updated the WordPress doctemplate review-packet smoke so its applied
  `next-warning` partial proves both names are visible on the reviewer path.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents applied partials as equivalent to
  iterating the variable and applying the partial to each item:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` compiles applied partial syntax into an
  `Iterate var (Partial ...)` template, so it follows ordinary loop variable
  rebinding:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- Upstream `Text.DocTemplates.Internal` renders `Iterate` through `withVariable`,
  which inserts `it` and also updates the variable path in the context:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, online
  conversion service, or live service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 56 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 57 assertions, 2 failures because applied partials exposed
  `it.title` / `it.source` but left `articles.title`, `warnings.source`, and
  `import.items.title` empty.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 57 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, explicit loop scoping, separators before or after pipes, `$it$`
availability, `$^$`, automatic multiline nesting, `$~$` breakable-space
whitespace reflow, parameter-free pipes, parameterized block pipes, Unicode
display-width padding, missing/null pipe handling, resource-map partial
discovery, path-style partial lookup, applied partial parsing, partial
recursion guards, braced directive tokenizer behavior, alpha overflow labels,
boolean false output rendering, Unicode identifier parsing, multiline control
boundary newline swallowing, empty standalone partial line swallowing,
deterministic map-pairs ordering, trailing separators after piped variables, or
included-partial final-LF omission. It only changes applied-partial context
rebinding to match the explicit-loop path.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and its loop-context helper. Full
doclayout width-sensitive wrapping, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
