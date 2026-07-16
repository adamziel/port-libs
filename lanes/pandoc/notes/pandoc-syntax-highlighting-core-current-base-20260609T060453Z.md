# Pandoc Syntax Highlighting Agda Handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T060453Z`
Base accepted HEAD: `11b5789183ebb8ab34ff922479caf161e9cc4881`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded syntax-highlighting support cluster for Agda proof and
review snippets:

- `SyntaxHighlighter` now maps `agda`, `agda2`, `agda-lang`, `agda-source`,
  `lagda`, `lagda-md`, `lagda-tex`, and `literate-agda` aliases to canonical
  `agda`.
- Added a native PHP Agda scanner for line/block comments, `{-# OPTIONS #-}`
  pragmas, module/import declarations, record constructors and fields, type
  signatures, `with` clauses, constructors, postulates, numbers, strings, and
  operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered Agda review packet so highlighted HTML blocks preserve style
  metadata and source-line anchors.

## Evidence

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $code = "module Review where\nrecord Packet : Set where\n  field title : Maybe String\n"; $r = $h->highlight($code, "agda"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("agda"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'
array (
  0 => NULL,
  1 => '',
  2 => 'unsupported-language',
)
```

Baseline focused suite:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2676 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2714 assertions, 0 failures

php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+38` focused assertions in
`SyntaxHighlighterTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader` fenced-code attributes, `AstNode` code
blocks, `WordPressBlockWriter` HTML block handoff, the existing syntax fixture,
and focused PHP tests. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for Coq, Idris, V,
Nim, shell-session transcripts, sed text payloads/print commands, Crystal,
Groovy, Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just,
Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX,
custom-theme metadata, token-title metadata, line-highlight metadata, HTML/PHP
islands, or unsupported-language fallback behavior. It owns only bounded Agda
alias and token handoff for fixture-backed WordPress review code blocks.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners,
Skylighting runtime highlighters, Agda compilers, external highlighters,
browser renderers, external converters, online services, live provider tests,
or live-service provider tests. Root harness not run for isolated micro-slice.

Useful follow-up: richer Agda nested-comment or unicode operator parity only if
upstream fixtures require it, or another unsupported Skylighting language
family with fixture-backed WordPress handoff coverage.
