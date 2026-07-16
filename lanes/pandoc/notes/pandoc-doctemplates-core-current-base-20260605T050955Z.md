# Pandoc doctemplates core current-base 2026-06-05T05:09:55Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` identifier parsing with upstream
  doctemplates Unicode identifier behavior.
- Variable path segments now accept Unicode letters and Unicode alphanumeric
  continuation characters, preserving the existing `_` and `-` continuation
  characters and the special leading `it` segment.
- Partial calls now accept Unicode resource names, so resource-map rendering
  can load multilingual partials such as `components/résumé.html`.
- Updated the WordPress doctemplate review-packet smoke with a multilingual
  résumé partial rendered from Unicode context keys.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents variable names as beginning with a
  letter and continuing with letters, numbers, `_`, `-`, and `.`, and documents
  partials as Pandoc's template subtemplates:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` parses variable identifier parts with
  Parsec letter/alphanumeric predicates and parses partial names from
  alphanumeric plus `_`, `-`, `.`, `/`, and `\` characters:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- No Pandoc binary, Haskell test binary, Cabal build, Word, LibreOffice,
  `zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
  browser renderer, roff, Typst, MathJax, KaTeX, online sanitizer, or online
  conversion service was executed.

## Evidence

- Rework note check found no current
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  paths.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 45 assertions, 0 failures.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 46 assertions, 0 failures.
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
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic multiline
nesting, `$~$` markers, parameter-free pipes, parameterized pipes, Unicode
display-width padding, missing/null pipe handling, inline partial arrays,
resource-map partial discovery, path-style partial lookup, applied partials,
partial final-newline handling, partial recursion guards, braced directive
tokenizer behavior, alpha overflow labels, or boolean false output rendering.
It only widens the parser from ASCII-only variable and partial call names to
the Unicode letter/alphanumeric identifier boundary used by upstream
doctemplates.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode
display-width helpers, or upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and PHP PCRE Unicode properties for bounded
identifier parsing. Full doclayout line wrapping for `$~$`, multiline control
line swallowing, filesystem-backed template discovery beyond the existing
resource map, writer-extension template selection, default-template parity,
richer parser source-location diagnostics, and full upstream Pandoc runner
parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
