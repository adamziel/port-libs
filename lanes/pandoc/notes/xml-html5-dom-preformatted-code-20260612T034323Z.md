# XML/HTML5 DOM Preformatted Code Blocks

Mapped one bounded XML/HTML5 DOM handoff slice for HTML preformatted code blocks.

- Added `pre` summaries that preserve raw preformatted text, byte length, line count, and SHA-256 provenance.
- Added direct `pre > code` review metadata for raw code text, class tokens, and `language-*`/`lang-*` language inference.
- Kept deterministic raw HTML and WordPress raw-block handoff native PHP only.
- Did not invoke Pandoc, Cabal/Haskell runners, browser renderers, online sanitizers, external validators, online services, live provider tests, or live-service provider tests.
