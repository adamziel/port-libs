# Pandoc Syntax Highlighting Idris Handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T052746Z`
Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded syntax-highlighting support cluster for Idris review
snippets:

- `SyntaxHighlighter` now maps `idris`, `idr`, `idris2`, `idris-lang`, and
  `idris-source` aliases to canonical `idris`.
- Added a native PHP Idris scanner for comments, `%` directives, module names,
  record declarations, type signatures, constructors, constants, keywords,
  strings, numbers, variables, and operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered Idris review packet so highlighted HTML blocks preserve style
  metadata and source-line anchors.

## Source Truth

This maps the accepted lane-local Pandoc/Skylighting handoff contract already
used by `SyntaxHighlighter`: language aliases are normalized to a canonical
language, highlighted HTML uses short token classes such as `kw`, `dt`, `fu`,
`cn`, `st`, `co`, `dv`, `op`, and `pp`, and unsupported languages preserve
escaped source text instead of executing it. The previous accepted V/Nim syntax
notes named Idris as a remaining unsupported Skylighting language family; this
slice makes that family countable with native PHP only.

## Evidence

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2614 assertions, 0 failures
```

Red probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $r = $h->highlight("module Review\nrecord Packet where\n  constructor MkPacket\n  title : Maybe String\n", "idris"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("idris"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'
array (
  0 => NULL,
  1 => '',
  2 => 'unsupported-language',
)
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2645 assertions, 0 failures
```

Focused delta: `+1` PHP PASS case and `+31` focused assertions in
`SyntaxHighlighterTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`SyntaxHighlighter`, `MarkdownReader` fenced-code attribute parsing,
`WordPressBlockWriter` HTML block handoff, the existing syntax fixture, and
focused PHP tests. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned upstream
sources and Haskell test executables.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for V, Nim,
shell-session transcripts, sed text payloads/print commands, Crystal, Groovy,
Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just, Fennel,
Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX, custom
theme metadata, token-title metadata, line-highlight metadata, unsupported
fallback behavior, or HTML/PHP island highlighting. It owns only bounded Idris
alias and token handoff for fixture-backed WordPress review code blocks.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners,
Skylighting runtime highlighters, Idris compilers, external highlighters,
browser renderers, external converters, online services, live provider tests,
or live-service provider tests. Root harness not run for isolated micro-slice.

Useful follow-up: Agda/Coq or another unsupported Skylighting language family,
or a deeper language-state edge not covered by Idris, V, Nim, shell-session,
sed, or HTML/PHP island support.
