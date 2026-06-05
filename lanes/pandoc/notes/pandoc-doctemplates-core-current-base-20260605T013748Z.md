# Pandoc doctemplates core current-base 2026-06-05T01:37:48Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `left`, `right`, and `center` block
  pipes with Pandoc display-column behavior by padding with the existing native
  `UnicodeText::padDisplay()` helper instead of codepoint length.
- Added focused doctemplate coverage for CJK double-width text, combining-mark
  labels, and emoji presentation clusters in block-pipe padding.
- Updated the WordPress doctemplate review-packet smoke with a multilingual
  warning source label so reviewer packet rows exercise display-width padding
  through the user-visible template path.

## Source Truth

- Pandoc template syntax documents `left`, `right`, and `center` as
  parameterized pipes for block formatting.
- Prior accepted charset/Unicode lane evidence records that Pandoc layout uses
  display width rather than byte or codepoint count, with CJK, combining-mark,
  and emoji display-width primitives available in native PHP.
- This slice reuses that existing bounded support component; it does not add a
  new template engine or a new Unicode subsystem.
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
  1 test file, 36 assertions, 0 failures.
- Red-first display-width probe before implementation:
  `php <<'PHP' ... $r->render('$title/left 6 "|" "|"$', ['title' => '漢字']) ... PHP`
  failed with `display-width padding mismatch: "|漢字    |"`; expected
  `|漢字  |`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 37 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Focused lane verification:
  `php tools/run-tests.php lanes/pandoc/tests` passed with 19 test files,
  5307 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` breakable-space
markers, parameter-free pipes, enumeration pipes, inline partial arrays,
resource-map partial discovery, applied partial rendering, partial final-newline
handling, or partial recursion guards. It only changes block-pipe padding width
calculation from codepoint count to the already accepted Unicode display-width
model.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `UnicodeText` display-width support inside the existing
`pandoc-doctemplates-core` renderer. Full doclayout line wrapping for `$~$`,
filesystem-backed template discovery, writer-extension template selection,
default-template parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
