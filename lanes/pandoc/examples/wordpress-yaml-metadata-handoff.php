<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = <<<'MARKDOWN'
The source export starts with a migration preface before metadata.

---
title: "Migration **Packet**" # source export title
author:
  - Data Liberation reviewer
  - "WordPress #import editor"
date: 2026-06-03
keywords: [migration, wordpress, metadata] # reviewer labels
reviewDefaults_: &review_defaults
  status: queued
  priority: 3
  labels: &review_labels [front-matter, wordpress]
review:
  <<: *review_defaults
  owner: !wp-reviewer "Import Desk"
source-uri: /exports/packet#front-matter
source-summary: >- # folded source note for reviewer queue
  Preserve front matter
  comments before rendering.
audit-note: |+ # keep final newline for audit packets
  YAML parser keeps this note.

aliases:
  labels: *review_labels
source-revision: !!str 007
references:
  - &source_reference
    id: source-export
    type: article-journal
    title: "Source: Metadata export"
    issued:
      date-parts:
        - - 2026
          - 6
          - 3
---

# Imported Body

---
review: {status: needs-review, priority: 2, labels: [qa, follow-up]}
summary: >- # later metadata block overrides the first review status
  Preserve front matter for reviewer handoff
  before rendering the imported body.
---

The block import keeps the source metadata available for audit tooling.
MARKDOWN;

$document = (new MarkdownReader())->read($markdown);
$meta = $document->attr('meta', []);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (($meta['review']['status'] ?? '') !== 'needs-review') {
        throw new RuntimeException('YAML metadata self-test missing later review override');
    }
    if (($meta['references'][0]['issued']['date-parts'][0] ?? []) !== [2026, 6, 3]) {
        throw new RuntimeException('YAML metadata self-test missing block-style date-parts');
    }
    if (($meta['aliases']['labels'] ?? []) !== ['front-matter', 'wordpress']) {
        throw new RuntimeException('YAML metadata self-test missing anchor alias labels');
    }
    if (($meta['authors'][1] ?? '') !== 'WordPress #import editor') {
        throw new RuntimeException('YAML metadata self-test stripped quoted author hash');
    }
    if (($meta['source-summary'] ?? '') !== 'Preserve front matter comments before rendering.') {
        throw new RuntimeException('YAML metadata self-test missing folded source comment summary');
    }
    if (($meta['summary'] ?? '') !== 'Preserve front matter for reviewer handoff before rendering the imported body.') {
        throw new RuntimeException('YAML metadata self-test missing later folded comment summary');
    }
    if (($meta['audit-note'] ?? '') !== "YAML parser keeps this note.\n") {
        throw new RuntimeException('YAML metadata self-test missing literal keep-chomp note');
    }
    if (($meta['source-uri'] ?? '') !== '/exports/packet#front-matter') {
        throw new RuntimeException('YAML metadata self-test stripped unspaced source hash');
    }
    if (($meta['source-revision'] ?? '') !== '007') {
        throw new RuntimeException('YAML metadata self-test missing tagged string revision');
    }
    if (!str_contains($blocks, '<h1 id="imported-body">Imported Body</h1>')) {
        throw new RuntimeException('YAML metadata self-test missing imported body heading');
    }

    echo "yaml metadata handoff self-test ok\n";
    return;
}

echo 'Title: ' . ($meta['title'] ?? '') . "\n";
echo 'Authors: ' . implode(', ', $meta['authors'] ?? []) . "\n";
echo 'Review status: ' . ($meta['review']['status'] ?? '') . "\n";
echo 'Review labels: ' . implode(', ', $meta['review']['labels'] ?? []) . "\n";
echo 'Keywords: ' . implode(', ', $meta['keywords'] ?? []) . "\n\n";
echo 'Source revision: ' . ($meta['source-revision'] ?? '') . "\n";
echo 'Reference: ' . ($meta['references'][0]['id'] ?? '') . ' / ' . ($meta['references'][0]['title'] ?? '') . "\n\n";
echo $blocks . "\n";
