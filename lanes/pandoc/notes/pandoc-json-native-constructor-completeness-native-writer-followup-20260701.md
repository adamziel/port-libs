# JSON/Native Constructor Completeness Native Writer Follow-Up

`PandocJsonNativeConstructorCompletenessFollowupTest.php` adds a focused
coverage slice for structural inline constructor sidecars when parent
paragraph wrappers are rebuilt.

The fixture reads current `Emph`, `Strong`, `Underline`, `Strikeout`,
`Superscript`, `Subscript`, `SmallCaps`, `Quoted`, `Span`, and `Note`
constructors through both JSON and native readers, rebuilds the containing
paragraph without its stale wrapper sidecar, and verifies both
`PandocJsonWriter` and `NativeWriter` preserve unchanged child constructor
payloads. It then edits one inline and checks that both writers regenerate the
edited constructor while keeping adjacent current sidecars intact.

This is a bounded native PHP JSON/native constructor-completeness follow-up
for `plib-9rsl4`. It does not invoke Pandoc, JSON filters, Cabal/Haskell
runners, browser renderers, Node tooling, online services, live providers, or
external validators.
