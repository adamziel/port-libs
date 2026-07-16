# Pandoc Syntax Highlighting Python Bytes Prefix Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260607T204917Z`
Accepted base: `5c6ad6b05a31e14db0b0d8415f0ee93984f83b0f`

## Scope

This slice owns one bounded syntax-highlighting support cluster for Python
bytes and raw-bytes string prefixes in WordPress code-review packets. It does
not shell out to Pandoc, Skylighting, Python, Cabal/Haskell runners, external
highlighters, browser renderers, JavaScript runtimes, online sanitizers, online
services, live provider tests, or live-service provider tests.

## Source Truth

Pandoc delegates code-block highlighting to Skylighting from the pinned Pandoc
highlighting surface, and Skylighting's Python syntax treats Python string
prefixes as part of the string literal token. This native PHP slice maps only
the bounded `b`, `B`, `br`, `rb`, and case-insensitive raw-bytes prefix handoff
needed by review snippets; it does not attempt full Python lexical parity.

Relevant source surfaces:

- `src/Text/Pandoc/Highlighting.hs` in upstream Pandoc
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
- Skylighting Python syntax-definition behavior for Python prefixed strings
- `lanes/pandoc/src/SyntaxHighlighter.php`

## Behavior Added

- Python string scanning now treats `b"..."`, `B"..."`, `br"..."`, and
  `rb"..."` forms as single bounded string tokens, including triple-quoted
  forms through the same prefix pattern.
- The WordPress syntax-highlighting fixture now includes a Python review block
  with BOM bytes and a raw-bytes regex string.
- The WordPress syntax handoff example self-test verifies the fixture-backed
  bytes/raw-bytes token handoff and style metadata.

## Evidence

Red-first direct probe before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new PortLibs\Pandoc\SyntaxHighlighter(); $r = $h->highlight("payload = b\"\\xff\"\npattern = rb\"data-\\d+\"", "py3"); echo $r["html"], "\n";'
<span class="va">payload</span> <span class="op">=</span> <span class="va">b</span><span class="st">&quot;\xff&quot;</span>
<span class="va">pattern</span> <span class="op">=</span> <span class="va">rb</span><span class="st">&quot;data-\d+&quot;</span>
```

Focused test after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1482 assertions, 0 failures
```

Example smoke after the implementation:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

## Status Delta

- Focused SyntaxHighlighter coverage: `1468 -> 1482` assertions.
- Focused PASS cases: `63 -> 64`.
- Lane `phpPass`: `1537 -> 1538`.
- Manifest mapped denominator: `1956 -> 1957`.

## Dependency Closure

No new support component is required. This slice reuses the native
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows plus the existing syntax-highlighting fixture/example.

## Non-Overlap

This slice avoids accepted syntax-highlighting work for CSS, Rust, SCSS/Sass,
Bash heredocs, PHPDoc, PHP heredoc/nowdoc, HCL/Terraform, Typst, AsciiDoc,
JSONC, LESS, Liquid, Elm, Kotlin, HTML embedded islands, GraphQL, CMake, nginx,
Twig, Mustache/Handlebars, Mermaid, SQL/PostgreSQL, Apache, reStructuredText,
and the prior Python decorator/type-hint coverage. It owns only bounded Python
bytes/raw-bytes string prefix token handoff.

## Follow-Up

Keep full Python lexical parity, f-string interpolation sub-tokenization,
byte-escape diagnostics, and broader embedded-language highlighting as separate
bounded syntax-highlighting slices.

Root harness: not run - isolated micro-slice.
