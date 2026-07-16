# Pandoc doctemplates core current-base 2026-06-05T06:14:33Z

## Slice

- Tightened `PortLibs\Pandoc\DocTemplate` bare partial rendering so an empty
  partial that appears alone on an indented template line removes that
  structural line instead of leaving an indented blank line.
- Preserved existing behavior for nonempty standalone partials, including
  automatic indentation of multiline partial output.
- Preserved inline partial behavior: multiline partials included next to other
  text are not re-indented or line-swallowed as if they were standalone blocks.
- Updated the WordPress doctemplate review-packet smoke with an optional empty
  `components/admin-note()` partial and a guard that rejects blank-line leakage
  between the header and the next visible review component.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents partials as Pandoc's subtemplate
  mechanism, final-newline omission for included partials, and automatic
  nesting for standalone multiline template values:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Parser` routes bare partials through
  `handleNesting True`, which treats beginning-of-line/end-of-line bare
  partials differently from ordinary interpolation:
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
  1 test file, 47 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 48 assertions, 1 failure because `${ maybe-warning() }` left an
  indented blank line before the next `<li>`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 50 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Full lane-focused verification:
  `php tools/run-tests.php lanes/pandoc/tests` passed with 20 test files,
  8007 assertions, 0 failures, and 683 local PASS lines.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
conditionals, loop scoping, separators, `$it$`, `$^$`, automatic nesting for
nonempty multiline values, `$~$` markers, parameter-free pipes,
parameterized pipes, Unicode display-width padding, missing/null pipe handling,
resource-map partial discovery, path-style partial lookup, applied partials,
partial final-newline handling, partial recursion guards, braced directive
tokenizer behavior, alpha overflow labels, boolean false output rendering,
Unicode identifier parsing, or multiline control boundary newline swallowing.
It only removes the surrounding template line for empty bare partials that are
otherwise alone on that line.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and in-memory resource map. Full doclayout
line wrapping for `$~$`, richer source-location diagnostics, filesystem-backed
template discovery beyond the existing resource map, writer-extension template
selection, default-template parity, and full upstream Pandoc runner parity
remain separate activation slices.

Root harness: not run - isolated micro-slice.
