# pandoc-syntax-highlighting-core-current-base-20260609T002017Z

Base accepted HEAD: `e681d9cd3726e0b2d0a8b66aaf879a79d22125f0`

## Behavior

- Added bounded native Protobuf syntax-highlighting support to `SyntaxHighlighter`.
- Normalizes `proto`, `proto2`, `proto3`, `protobuf`, `protobuf3`, `protocol-buffer`, and `protocol-buffers` to the `protobuf` language.
- Tokenizes Protobuf review schemas for comments, strings, syntax/package/import/options, messages, oneof/map fields, service/RPC signatures, booleans, numeric values, field/option identifiers, and operators.
- Extended the WordPress syntax-highlighting fixture and example with a numbered Protobuf import-review schema so the WordPress raw HTML handoff preserves source-line metadata and the active highlight style.

## Source Truth

Pandoc syntax highlighting is delegated upstream through Skylighting. The bounded local contract was derived from Skylighting's Protobuf syntax definition (`skylighting-core/xml/protobuf.xml`), which advertises Protobuf/protocol-buffer aliases and token groups for comments, strings, keywords, types, numbers, and operators. This slice ports the fixture-backed alias/style/token handoff only; it does not attempt full Skylighting parser-state parity.

Source inspected: https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/protobuf.xml

## Verification

- Rework-note check: no current `port-pandoc-*.needs-lane-rework.md` notes existed for this lane.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` failed with `1 test files, 2137 assertions, 2 failures` because proto/protobuf aliases were unsupported.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php` passed with `1 test files, 2240 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test` passed with `syntax highlighting handoff self-test ok`.
- PHP lint: changed PHP files passed `php -l`.
- Whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This owns only Protobuf syntax highlighting. It does not duplicate accepted CSS, Rust, AsciiDoc, HCL/Terraform, Typst, SQL, PHP/PHPDoc, Meson, Justfile, or other syntax-highlighting slices, and it does not alter shared Markdown parsing, HTML serialization, DOCX/ODF/EPUB/PDF, YAML, Unicode, CSL/BibTeX, table, math, archive, OPC, or ZIP behavior.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `SyntaxHighlighter` scanning, MarkdownReader fenced-code metadata, AST code blocks, the existing syntax fixture/example path, and WordPress raw HTML handoff. Full upstream Pandoc/Skylighting runner parity remains a separate upstream-runner dependency concern gated on a hydrated pinned checkout and a reviewed non-mutating Cabal plan.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting runtime, `protoc`, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Follow-Up

A next non-overlapping syntax slice could cover another unclaimed Skylighting language or deepen Protobuf parser-state behavior for extensions, reserved ranges, options, and proto2/proto3 distinctions while staying native PHP and external-tool free.
