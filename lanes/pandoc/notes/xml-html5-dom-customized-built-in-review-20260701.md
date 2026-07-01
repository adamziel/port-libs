# XML/HTML5 DOM Customized Built-In Review

Slice: `plib-utpnp`

`XmlHtmlDom::summarizeHtmlFragment()` now emits bounded reviewer metadata for
HTML `is="..."` customized built-in element hooks:

- valid upgrade names are marked as customized built-in elements;
- reserved custom-element names such as `font-face` are rejected for upgrade
  validity while preserving the raw source value;
- empty or syntactically invalid names carry deterministic issue codes.

The serializer output is unchanged. This is metadata-only DOM provenance for
review queues and WordPress raw HTML handoff; it does not implement custom
element lifecycle behavior, browser parsing, JavaScript execution, external
validators, Pandoc shell-out, or live services.
