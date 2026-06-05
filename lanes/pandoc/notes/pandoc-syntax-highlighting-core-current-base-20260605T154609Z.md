# Pandoc Syntax Highlighting Go Core Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T154609Z`
Accepted base: `ec20b7c0e1dcc9907649597c48f96fae7a35b178`

## Scope

This slice owns one bounded syntax-highlighting support cluster for Go review
snippets in WordPress import packets. It does not shell out to Pandoc,
Skylighting, Go tooling, external highlighters, browser renderers, JavaScript,
online sanitizers, or online services.

## Source Truth

Pandoc delegates code-block highlighting through Skylighting at the pinned
Pandoc source surface. The pinned Pandoc `Text.Pandoc.Highlighting` module maps
`go` to Go for listings interop and asks Skylighting to tokenize the selected
code-block class. Skylighting's Go XML syntax definition carries the same
bounded category set used here: Go keywords, primitive types, builtins,
constants, strings, comments, and operators.

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
- `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/go.xml`

The PHP port maps that contract into a bounded native token handoff rather than
a full Skylighting runtime.

## Behavior Added

- `go`, `golang`, and `language-go` aliases normalize into supported Go
  highlighting.
- Go snippets now tokenize package/import declarations, type/struct/map forms,
  primitive types, builtins, constants, raw/interpreted strings, comments,
  selectors, function calls, goroutines, and operators.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered Go review block with WordPress raw HTML style metadata.

## Evidence

Baseline focused test before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 652 assertions, 0 failures
```

Red-first unsupported-language probe before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $code = "package main\nfunc NormalizeTitle(title string) string { return title }\n"; $result = $h->highlight($code, "go"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("go"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("golang"), $result["language"], $result["diagnostics"]]); echo "\n";'
array ( 0 => NULL, 1 => NULL, 2 => '', 3 => array ( 0 => array ( 'code' => 'unsupported-language', 'message' => 'No bounded native syntax definition is available for \'go\'', ), ), )
```

Focused test after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 681 assertions, 0 failures
```

Example smoke after the implementation:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Additional required checks:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'
lanes/pandoc/lane-status.json json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json json ok

git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- Focused SyntaxHighlighter coverage: `652 -> 681` assertions.
- Focused PASS cases: `35 -> 36`.
- Lane `phpPass`: `979 -> 980`.
- Manifest mapped denominator: `1434 -> 1435`.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows. Full upstream runner parity remains blocked on hydrated Pandoc and
Cabal runner setup, and no Haskell runner was executed.

## Non-Overlap

This slice does not repeat existing CSS, Rust, Nix, SCSS/Sass, TOML, XML, HTML,
SQL, Haskell, TeX, Markdown, Ruby, Lua, TypeScript, Python, C/C++, Dockerfile,
Makefile, INI, Perl, Java, or custom-theme syntax-highlighting handoffs. It owns
only bounded Go alias and token handoff.

## Follow-Up

Full Skylighting XML state-machine parity, Go build-tag handling, richer raw
string and rune escape edge cases, embedded-language highlighting, writer-wide
default highlighting policy, and full upstream runner dependency planning remain
separate bounded follow-up slices.

Root harness: not run - isolated micro-slice.
