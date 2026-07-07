# GitHub Flavored Markdown feature packet

This paragraph uses **bold**, _italic_, ~~strikethrough~~, `inline code`, a bare URL https://github.com/openai, and a mention-like token @octocat.

## Task list

- [x] Parse GFM task list syntax
- [ ] Preserve unchecked tasks
- [x] Render nested formatting with **bold and _italic_ text**

## Table syntax

| Feature | Rendered example |
| --- | --- |
| Bold | **This is bold text** |
| Italic | _This text is italicized_ |
| Strikethrough | ~~This was mistaken text~~ |
| Inline code | `git status` |
| Link | [OpenAI](https://openai.com/) |

## Quote and alert-style block

> [!NOTE]
> This block intentionally uses GitHub-style alert syntax without escaping it.

## Autolinks and references

See https://github.github.com/gfm/ and [the CommonMark spec][commonmark].

[commonmark]: https://spec.commonmark.org/