# Pandoc Syntax Highlighting V Language Handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T051011Z`
Base accepted HEAD: `516b4c2368ab923eeb7c71f762618468a7a4d437`
Date: 2026-06-09 UTC

## Scope

Implemented one bounded syntax-highlighting support cluster for V language
review snippets:

- `SyntaxHighlighter` now maps `v`, `vlang`, `v-source`, and `v-language`
  aliases to canonical `v`.
- Added a native PHP V scanner for comments, module/import declarations,
  attributes, structs, datatypes, option/result punctuation, compile-time
  guards, strings, JSON decode calls, maps, functions, variables, and
  operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with a
  numbered V review packet so highlighted HTML blocks preserve style metadata
  and source-line anchors.

## Evidence

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2582 assertions, 0 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 2614 assertions, 0 failures

php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok

php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $code = "module review\nfn main() { println(\"ok\") }"; $r = $h->highlight($code, "v"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("v"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'
array (
  0 => 'v',
  1 => 'v',
  2 => NULL,
)
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
no output
```

Status delta:

- `phpPass`: `2354 -> 2355`
- mapped denominator: `2749 -> 2750`
- focused assertions: `2582 -> 2614` (`+32`)
- added manifest keys: `syntaxHighlightingVLanguageCases`,
  `mappedSyntaxHighlightingVLanguageCases`,
  `syntaxHighlightingVLanguageAssertions`

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader` fenced-code attributes,
`WordPressBlockWriter` HTML block handoff, the existing syntax fixture, and
focused PHP tests. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring hydrated pinned sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted syntax-highlighting coverage for Nim,
shell-session transcripts, sed text payloads/print commands, Crystal, Groovy,
Pascal/Delphi, Common Lisp, D, Fortran, Tcl, Protobuf, Meson/Just, Fennel,
Raku, Objective-C, Erlang, CSV/TSV, Scheme/Racket, Vimscript, BibTeX,
custom-theme metadata, token-title metadata, line-highlight metadata, or
unsupported-language fallback behavior.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners,
Skylighting runtime highlighters, V compilers, external highlighters, browser
renderers, external converters, online services, live provider tests, or
live-service provider tests. Root harness not run for isolated micro-slice.

Useful follow-up: Idris or another unsupported Skylighting alias family, or a
deeper language-state edge not covered by V, Nim, shell-session, or sed.
