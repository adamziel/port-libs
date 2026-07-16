# Pandoc syntax highlighting shell-session handoff

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260609T043140Z`
Base accepted HEAD: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`
Date: 2026-06-09 UTC

## Scope

Mapped one bounded syntax-highlighting behavior cluster: shell-session
transcripts now normalize `shell-session`, `shellsession`, `bash-session`,
`console-session`, and `sh-session` to `shellsession`. Prompt prefixes are
highlighted as region spans, command tails reuse the native Bash tokenizer, and
non-prompt transcript output is highlighted as information spans for WordPress
review handoff.

The fixture-backed WordPress example includes a `wp post` shell transcript with
line numbering and style metadata, so review blocks preserve prompt commands,
output lines, anchors, and style selection without executing shell snippets.

## Evidence

Baseline focused syntax test:

`php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`

Result before this patch: `1 test files, 2528 assertions, 0 failures`.

Red probe before implementation:

`php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $r = $h->highlight("$ wp post list --post_type=post\n42", "shell-session"); var_export([PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("shell-session"), $r["language"], $r["diagnostics"][0]["code"] ?? null]); echo "\n";'`

Result before this patch:

`array ( 0 => NULL, 1 => '', 2 => 'unsupported-language', )`

Final focused syntax test:

`php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`

Result after this patch: `1 test files, 2550 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`

Result after this patch: `syntax highlighting handoff self-test ok`.

Status delta:

- `phpPass`: `2305 -> 2306`
- mapped denominator: `2705 -> 2706`
- new focused assertions: `+22`
- new manifest keys: `syntaxHighlightingShellSessionCases`,
  `mappedSyntaxHighlightingShellSessionCases`,
  `syntaxHighlightingShellSessionAssertions`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and WordPress HTML block
handoff paths. Full upstream Pandoc/Skylighting runner parity remains a
separate upstream-runner dependency task requiring a hydrated Pandoc checkout
and Haskell test executables.

## Non-Overlap

This does not repeat the accepted sed print/append/change/insert handoff,
HTML/PHP island highlighting, Crystal/Groovy/Common Lisp/Pascal language
families, or existing Bash alias behavior. The existing `console` alias remains
bounded Bash highlighting; only explicit transcript aliases map to
`shellsession`.

## Exclusions And Follow-Up

Not run: Pandoc, Cabal solver/build/test commands, Haskell runners, Skylighting
runtime highlighters, shell command execution, browser renderers, external
converters, online services, live provider tests, or live-service provider
tests.

Useful follow-up: shell continuation prompts and exit-status transcript
metadata, another uncovered alias family, or deeper sed delimiter/address
parsing.
