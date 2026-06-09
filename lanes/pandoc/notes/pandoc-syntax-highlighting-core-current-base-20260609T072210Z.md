# Pandoc Syntax Highlighting F# Handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T072210Z`
Base accepted HEAD: `93c7fe92d8764429cde901a465ac3a9266aec0d4`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded syntax-highlighting support cluster for F# review
snippets:

- `SyntaxHighlighter` now maps `fsharp`, `F#`, `fs`, `fsi`, `fsx`,
  `fsharp-source`, and related FSharp aliases to canonical `fsharp`.
- Added a native PHP F# scanner for comments, attributes, modules, `open`
  declarations, records, union cases, match arms, async blocks, strings,
  interpolated/verbatim strings, numbers, datatypes, functions, variables, and
  operators.
- Extended the WordPress syntax-highlighting fixture, focused test, and example
  smoke with a numbered F# review packet so highlighted HTML blocks preserve
  style metadata and source-line anchors.

## Evidence

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $code = "module WP.Import.Review\ntype ReviewPacket = { SourceId: int; Title: string option }\nlet normalizeTitle packet = defaultArg packet.Title \"Untitled\"\n"; $r = $h->highlight($code, "fsx"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("fsx"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'
array (
  0 => NULL,
  1 => '',
  2 => 'unsupported-language',
)
```

Baseline focused suite:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2783 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2819 assertions, 0 failures

php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+36` focused assertions in
`SyntaxHighlighterTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader` fenced-code attributes, `AstNode` code
blocks, `WordPressBlockWriter` HTML block handoff, the existing syntax fixture,
and focused PHP tests. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for PureScript,
Agda, Coq, Idris, V, Nim, shell-session transcripts, sed text payloads/print
commands, Crystal, Groovy, Pascal/Delphi, Common Lisp, D, Fortran, Tcl,
Protobuf, Meson/Just, Fennel, Raku, Objective-C, Erlang, CSV/TSV,
Scheme/Racket, Vimscript, BibTeX, custom-theme metadata, token-title metadata,
line-highlight metadata, HTML/PHP islands, or unsupported-language fallback
behavior. It owns only bounded F# alias and token handoff for fixture-backed
WordPress review code blocks.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners,
Skylighting runtime highlighters, F# compilers, external highlighters, browser
renderers, external converters, online services, live provider tests, or
live-service provider tests. Root harness not run for isolated micro-slice.

Useful follow-up: another unsupported Skylighting language family with
fixture-backed WordPress handoff coverage, or deeper F# token-state parity such
as nested block comments and compiler directive regions only if upstream
fixtures require it.
