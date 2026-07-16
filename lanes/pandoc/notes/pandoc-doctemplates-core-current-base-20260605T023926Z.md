# Pandoc doctemplates core current-base 2026-06-05T02:39:26Z

## Slice

- Taught `PortLibs\Pandoc\DocTemplate` to find the end of a braced
  `${ ... }` directive while respecting double-quoted pipe arguments.
- This lets parameterized block pipes use quoted brace borders such as
  `${ title/center 12 "{" "}" }` without truncating the directive at the quoted
  `}`.
- Updated the WordPress doctemplate review-packet smoke so reviewer source
  labels render through a braced directive with `{...}` padding.

## Source Truth

- Pandoc template syntax supports brace-delimited `${...}` directives and
  parameterized `left`, `right`, and `center` block pipes with quoted border
  arguments.
- Prior accepted doctemplate slices already mapped parameterized block pipes,
  partials, final-newline stripping, recursion guards, and breakable-space
  markers. This slice only fixes the tokenizer boundary when those existing
  block-pipe arguments contain a closing brace.
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
  1 test file, 40 assertions, 0 failures.
- Red-first focused run after adding the new expectation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed on
  `renders braced pandoc doctemplate pipe arguments containing closing braces`
  with `Unclosed doctemplate pipe quoted string`.
- Red-first WordPress smoke after adding the braced review-label template:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  failed with `Unclosed doctemplate pipe quoted string`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 41 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` breakable-space
markers, parameter-free pipes, enumeration pipes, Unicode display-width
padding, inline partial arrays, resource-map partial discovery, applied partial
rendering, partial final-newline handling, or partial recursion guards. It only
changes how braced directive tokenization locates the closing `}` when quoted
pipe arguments contain literal braces.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and keeps all behavior in the
lane-local tokenizer/parser. Full doclayout line wrapping for `$~$`,
filesystem-backed template discovery, writer-extension template selection,
default-template parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
