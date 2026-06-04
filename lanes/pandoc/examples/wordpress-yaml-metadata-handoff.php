<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
The source export starts with a migration preface before metadata.

---
title: "Migration **Packet**"
author:
  - Data Liberation reviewer
  - WordPress import editor
date: 2026-06-03
keywords: [migration, wordpress, metadata]
review: {status: queued, priority: 3, labels: [front-matter, wordpress]}
references: [{id: source-export, type: article-journal, title: "Source: Metadata export", issued: {date-parts: [[2026, 6, 3]]}}]
---

# Imported Body

---
review: {status: needs-review, priority: 2, labels: [qa, follow-up]}
summary: >
  Preserve front matter for reviewer handoff
  before rendering the imported body.
---

The block import keeps the source metadata available for audit tooling.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$meta = $document->attr('meta', []);

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Review labels: ' . implode(', ', $meta['review']['labels'] ?? []) . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo 'Reference: ' . ($meta['references'][0]['id'] ?? '') . ' / ' . ($meta['references'][0]['title'] ?? '') . "\n\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
