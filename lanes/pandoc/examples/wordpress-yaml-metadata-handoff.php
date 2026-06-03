<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
---
title: "Migration **Packet**"
author:
  - Data Liberation reviewer
  - WordPress import editor
date: 2026-06-03
keywords: [migration, wordpress, metadata]
review:
  status: needs-review
  priority: 2
summary: >
  Preserve front matter for reviewer handoff
  before rendering the imported body.
---

# Imported Body

The block import keeps the source metadata available for audit tooling.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$meta = $document->attr('meta', []);

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo (new WordPressBlockWriter())->write($document) . "\n";
