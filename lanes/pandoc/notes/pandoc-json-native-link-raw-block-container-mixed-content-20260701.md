# JSON/Native Link Raw Block-Container Mixed Content

`PandocJsonNativeAstTest.php` now carries a focused JSON/native fixture for
mixed `Link`, `RawInline`, and `RawBlock` payloads adjacent to nested
`BlockQuote`, `Div`, and `Note` block containers.

The fixture verifies that JSON and native writers flush leading and trailing
inline runs into valid `Plain` blocks around raw block payloads and nested block
containers, then checks JSON/native reader round trips keep link targets, raw
inline payloads, generic raw block payloads, and block-only container child
lists intact.

This is a native PHP fixture-only follow-up for `plib-ftnaq`; it does not invoke
Pandoc, JSON filters, Cabal/Haskell runners, browser renderers, Node tooling,
online services, live providers, or external validators.
