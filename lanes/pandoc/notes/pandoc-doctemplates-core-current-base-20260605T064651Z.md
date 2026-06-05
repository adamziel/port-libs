# Pandoc doctemplates core current-base 2026-06-05T06:46:51Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` map handling for the predefined
  `pairs` pipe with upstream doctemplates' deterministic map-key order.
- Associative PHP arrays now sort keys with string ordering before producing
  `{key, value}` pair rows. PHP list arrays keep their existing 1-based pair
  indexes and source order.
- Updated the WordPress doctemplate review-packet smoke with an intentionally
  out-of-order metadata map so review fields render stably as
  `alpha`, `review-id`, then `zeta`.

## Source Truth

- Hackage `doctemplates-0.11.0.1` documents `pairs` as a predefined pipe that
  converts maps or arrays into key/value maps:
  https://hackage.haskell.org/package/doctemplates
- Upstream `Text.DocTemplates.Internal` stores contexts in `Data.Map` and
  builds map pairs with `M.toList`, giving deterministic key ordering before
  rendering:
  https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Internal.hs
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
  1 test file, 50 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with
  1 test file, 51 assertions, 1 failure because associative map pairs rendered
  in PHP insertion order (`zeta`, `alpha`, `review-id`) instead of upstream
  key order.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 51 assertions, 0 failures.
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
nesting, `$~$` markers, parameter-free pipe presence, parameterized pipes,
Unicode display-width padding, missing/null pipe handling, resource-map
partial discovery, path-style partial lookup, applied partials, partial
final-newline handling, partial recursion guards, braced directive tokenizer
behavior, alpha overflow labels, boolean false output rendering, Unicode
identifier parsing, multiline control boundary newline swallowing, or empty
standalone partial line swallowing. It only changes associative-map ordering
inside the `pairs` pipe.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, charset/Unicode, or
upstream-runner dependency audit behavior.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`pandoc-doctemplates-core` renderer and in-memory context arrays. Full
doclayout line wrapping for `$~$`, richer source-location diagnostics,
filesystem-backed template discovery beyond the existing resource map,
writer-extension template selection, default-template parity, and full
upstream Pandoc runner parity remain separate activation slices.

Root harness: not run - isolated micro-slice.
