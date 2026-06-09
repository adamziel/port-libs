# pandoc-syntax-highlighting-core-current-base-20260609T012742Z

Lane: `pandoc`
Accepted base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Scope

This slice adds bounded native syntax-highlighting line-highlight metadata
handoff under `lanes/pandoc/**`.

- `SyntaxHighlighter` now accepts `highlight-lines`, `highlightLines`,
  `data-highlight-lines`, `line-highlight`, and `hl_lines` style metadata from
  code block attributes or direct options.
- Relative line ranges such as `2,4-5` are shifted by `startFrom`; absolute
  ranges remain available with `highlight-lines-absolute`.
- Highlighted source rows render with `highlighted-line` and
  `data-pandoc-line-highlight` attributes in both direct highlighted HTML and
  WordPress raw HTML review blocks.
- The WordPress syntax-highlighting fixture and example now include a numbered
  PHP review snippet with highlighted lines.

## Source Truth

Pandoc preserves fenced-code attributes, language classes, source-line start
metadata, and highlighted HTML handoff through its syntax-highlighting path.
This patch ports the bounded PHP support-library contract for reviewer-visible
highlighted source rows. It does not implement a full Skylighting formatter or
run upstream Haskell code.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting,
external highlighter, browser renderer, JavaScript runtime, online service,
live provider test, or live-service provider test was executed.

## Verification

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` notes existed.
- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  passed with `1 test files, 2272 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  passed with `1 test files, 2292 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  passed with `syntax highlighting handoff self-test ok`.
- PHP lint passed for changed PHP files.
- `git diff --check -- lanes/pandoc` passed.

Focused assertion delta: `+20`.
Focused PHP PASS delta: `+1`.
Mapped denominator delta: `2451 -> 2452`.
Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native `SyntaxHighlighter`,
`MarkdownReader`, `AstNode`, `WordPressBlockWriter`, the existing syntax
fixture, focused `SyntaxHighlighterTest.php`, and the WordPress syntax handoff
example.

## Non-Overlap

This does not add another language alias/token cluster and avoids accepted
syntax-highlighting slices for CSS, Rust, AsciiDoc, HCL/Terraform, Typst,
Objective-C, Raku, Fennel, Meson/Justfile, Protobuf, Tcl, custom themes,
token-title metadata, and unsupported-language fallback behavior. It owns only
line-highlight range metadata and rendered source-row handoff.

## Follow-Up

Choose another non-overlapping syntax-highlighting support gap, such as an
unclaimed Skylighting alias family or richer bounded style/token metadata,
while keeping the same no-external-runner boundary unless a future slice is
explicitly assigned as an upstream runner dependency audit.
