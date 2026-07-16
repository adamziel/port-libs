# XML/HTML5 DOM Dialog Closedby Review - 2026-06-17

Scope: HTML/HTML5 DOM recovery only.

Implemented bounded `XmlHtmlDom` reviewer metadata for the HTML `dialog closedby` attribute. Dialog summaries now normalize `any`, `closerequest`, and `none`, surface invalid or missing values as the spec auto state, expose modal/modeless auto defaults for review, and keep deterministic raw HTML serialization for WordPress handoff.

No Pandoc executable, browser renderer, external validator, online service, or live provider is required for this slice.
