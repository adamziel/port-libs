# Pandoc doctemplates core current-base 2026-06-05T02:08:05Z

## Slice

- Aligned `PortLibs\Pandoc\DocTemplate` `alpha` pipe overflow with Pandoc-style
  alphabetic enumeration: after `z`, labels now continue as `aa`, `ab`, `az`,
  `ba`, `zz`, and `aaa` instead of wrapping back to `a`.
- Kept non-positive and non-numeric pipe inputs unchanged.
- Added focused doctemplate coverage for lowercase and uppercase alpha overflow
  chains.
- Updated the WordPress doctemplate review-packet smoke so a 27-item reviewer
  warning checklist emits marker `Z.` and then `AA.` through the visible
  template path.

## Source Truth

- Pandoc template syntax documents `alpha` as a predefined template pipe for
  alphabetic markers.
- Accepted lane evidence for Pandoc Markdown writer ordered-list markers already
  records alphabetic overflow as `aa`, `ab`, `AA`, and `AB` after `z`/`Z`.
  This slice applies the same bounded alphabetic marker contract to the
  doctemplate `alpha` pipe.
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
  1 test file, 37 assertions, 0 failures.
- Red-first alpha-overflow probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... [25, 26, 27, 28, 52, 53] ...'`
  failed with `alpha overflow mismatch: [y][z][a][b][z][a]`; expected
  `[y][z][aa][ab][az][ba]`.
- Focused verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with
  1 test file, 40 assertions, 0 failures.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This slice does not repeat accepted doctemplate comments, delimiters,
interpolated variables, boolean false rendering, conditionals, loops,
separators, `$it$`, `$^$`, automatic multiline nesting, `$~$` breakable-space
markers, parameter-free pipes, Roman numerals, block padding pipes, display
width padding, inline partial arrays, resource-map partial discovery, applied
partial rendering, partial final-newline handling, or partial recursion guards.
It only changes alpha pipe labels after `z`.

It does not touch ZIP/OPC package primitives, YAML metadata, Citation/CSL,
BibTeX/CSL, Markdown/HTML readers, Markdown/WordPress writers, DOCX/ODT/EPUB
or legacy-DOC parsing, table geometry, math conversion, PDF handoff planning,
archive compression, syntax highlighting, XML/HTML5 DOM, or upstream-runner
dependency audit behavior.

## Dependency Closure

No new external support component is needed. This reuses the existing native
PHP `pandoc-doctemplates-core` renderer and applies the already accepted
alphabetic marker overflow model locally. Full doclayout wrapping for `$~$`,
filesystem-backed template discovery, writer-extension template selection,
default-template parity, and full upstream Pandoc runner parity remain separate
activation slices.

Root harness: not run - isolated micro-slice.
