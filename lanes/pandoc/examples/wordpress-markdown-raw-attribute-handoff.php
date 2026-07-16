<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
# Raw Attribute Review

Raw TeX source: `\foo{there}`{=latex}

Raw HTML source: *Hi `<mark data-source="batch-42">there</mark>`{=html}*

```{=html}
<section data-source="batch-42">Imported raw review block</section>
```

```{=opml}
<outline text="Legacy WordPress source"/>
```
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);

echo (new WordPressBlockWriter())->write($document) . "\n";
