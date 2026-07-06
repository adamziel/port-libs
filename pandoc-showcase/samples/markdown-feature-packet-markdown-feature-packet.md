---
title: Pandoc Markdown feature packet
author:
  - Migration Team
keywords: [markdown, pandoc, blocks]
---

# Pandoc Markdown feature packet {#pandoc-feature-packet .review}

Term
: Definition with *emphasis*, **strong text**, and an inline footnote.^[Inline
  note with continuation text.]

1. Ordered item with a nested task.
   - [x] Preserve extension syntax
   - [ ] Review block output

| Feature | Writer expectation |
|:--|--:|
| pipe table | table block |
| footnote | generated note |

::: warning
Fenced div content with a [reference link][wp].
:::

```php
echo "pandoc markdown";
```

[wp]: https://wordpress.org/