# Pandoc Syntax Highlighting PureScript Handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T061649Z`
Base accepted HEAD: `54e4f08a09f2e83c9a94575366cb4582953b41b9`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded syntax-highlighting support cluster for PureScript
review snippets:

- `SyntaxHighlighter` now maps `purescript`, `pure-script`,
  `purescript-source`, and `purs` aliases to canonical `purescript`.
- Added a native PHP PureScript scanner for line/block comments, module/import
  declarations, `newtype`/record signatures, datatypes, constructors, record
  labels, strings, numbers, functions, and operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered PureScript review packet so highlighted HTML blocks preserve style
  metadata and source-line anchors.

## Evidence

Baseline focused suite:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2714 assertions, 0 failures
```

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $h=new PortLibs\Pandoc\SyntaxHighlighter(); $code="module Review.Import where\nnewtype ReviewPacket = ReviewPacket { title :: Maybe String }\nnormalizeTitle packet = case packet.title of\n  Just raw -> raw\n  Nothing -> \"Untitled\"\n"; $r=$h->highlight($code,"purescript"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("purescript"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'
array (
  0 => NULL,
  1 => '',
  2 => 'unsupported-language',
)
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2742 assertions, 0 failures

php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+28` focused assertions in
`SyntaxHighlighterTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader` fenced-code attributes, `AstNode` code
blocks, `WordPressBlockWriter` HTML block handoff, the existing syntax fixture,
and focused PHP tests. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for Agda, Coq,
Idris, V, Nim, shell-session transcripts, sed text payloads/print commands,
Crystal, Groovy, Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf,
Meson/Just, Fennel, Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket,
Vimscript, BibTeX, custom-theme metadata, token-title metadata, line-highlight
metadata, HTML/PHP islands, or unsupported-language fallback behavior. It owns
only bounded PureScript alias and token handoff for fixture-backed WordPress
review code blocks.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners,
Skylighting runtime highlighters, PureScript compilers, external highlighters,
browser renderers, external converters, online services, live provider tests,
or live-service provider tests. Root harness not run for isolated micro-slice.

Useful follow-up: another unsupported Skylighting language family with
fixture-backed WordPress handoff coverage, or deeper PureScript language-state
parity such as nested block comments only if upstream fixtures require it.
