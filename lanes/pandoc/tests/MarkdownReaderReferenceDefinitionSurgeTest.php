<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$findFirstNode = null;
$findFirstNode = static function (AstNode $node, string $type) use (&$findFirstNode): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $findFirstNode($child, $type);
        if ($match->type === $type) {
            return $match;
        }
    }

    return new AstNode('missing');
};

$readFirstNode = static function (string $markdown, string $type) use ($findFirstNode): AstNode {
    return $findFirstNode((new MarkdownReader())->read($markdown), $type);
};

$slug = static function (string $value): string {
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value);

    return trim($slug, '-') ?: 'case';
};

$referenceMarkdown = static function (string $sourceLabel, string $url, string $title, int $mode): array {
    if ($mode === 0) {
        return [
            "[visible {$url}][{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"{$title}\"",
            "visible {$url}",
        ];
    }

    if ($mode === 1) {
        return [
            "[{$sourceLabel}][]\n\n[{$sourceLabel}]: {$url} \"{$title}\"",
            $sourceLabel,
        ];
    }

    return [
        "[{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"{$title}\"",
        $sourceLabel,
    ];
};

$tests = [];

$escapedCloseBracketLabels = [
    'release-review' => ['release \] packet', 'release ] packet'],
    'audit-note' => ['audit \] note', 'audit ] note'],
    'migration-source' => ['migration \] source', 'migration ] source'],
    'entity-catalog' => ['entity \] catalog', 'entity ] catalog'],
    'link-target' => ['link \] target', 'link ] target'],
    'image-source' => ['image \] source', 'image ] source'],
    'title-review' => ['title \] review', 'title ] review'],
    'wordpress-handoff' => ['wordpress \] handoff', 'wordpress ] handoff'],
    'html-entity' => ['html \] entity', 'html ] entity'],
    'escaped-label' => ['escaped \] label', 'escaped ] label'],
    'reference-map' => ['reference \] map', 'reference ] map'],
    'shortcut-ref' => ['shortcut \] ref', 'shortcut ] ref'],
    'collapsed-ref' => ['collapsed \] ref', 'collapsed ] ref'],
    'inline-ref' => ['inline \] ref', 'inline ] ref'],
    'packet-id' => ['packet \] id', 'packet ] id'],
    'source-id' => ['source \] id', 'source ] id'],
    'review-id' => ['review \] id', 'review ] id'],
    'asset-id' => ['asset \] id', 'asset ] id'],
    'figure-id' => ['figure \] id', 'figure ] id'],
    'appendix-id' => ['appendix \] id', 'appendix ] id'],
];

$index = 0;
foreach ($escapedCloseBracketLabels as $name => [$sourceLabel, $expectedLabel]) {
    $tests["maps upstream escaped close bracket reference definition label {$name}"] = static function (TestRunner $t) use ($readFirstNode, $referenceMarkdown, $slug, $name, $sourceLabel, $expectedLabel, $index): void {
        $url = '/escaped-definition-' . $slug($name);
        $title = 'Escaped definition ' . $name;
        [$markdown, $expectedText] = $referenceMarkdown($sourceLabel, $url, $title, $index % 3);
        $link = $readFirstNode($markdown, 'link');

        $t->same('link', $link->type);
        $t->same($url, $link->attr('url'));
        $t->same($title, $link->attr('title'));
        $t->same(str_replace('\]', ']', $expectedText), $link->children[0]->attr('text'));
        $t->same($expectedLabel, str_replace('\]', ']', $sourceLabel));
    };
    $index++;
}

$nestedLabels = [
    'release-nested' => 'release [candidate] packet',
    'audit-nested' => 'audit [trail] note',
    'migration-nested' => 'migration [batch] source',
    'entity-nested' => 'entity [catalog] record',
    'link-nested' => 'link [target] record',
    'image-nested' => 'image [source] record',
    'title-nested' => 'title [review] source',
    'wordpress-nested' => 'wordpress [handoff] source',
    'html-nested' => 'html [entity] source',
    'escaped-nested' => 'escaped [label] source',
    'reference-nested' => 'reference [map] source',
    'shortcut-nested' => 'shortcut [ref] source',
    'collapsed-nested' => 'collapsed [ref] source',
    'inline-nested' => 'inline [ref] source',
    'packet-nested' => 'packet [id] source',
    'source-nested' => 'source [id] packet',
];

$index = 0;
foreach ($nestedLabels as $name => $sourceLabel) {
    $tests["maps upstream balanced nested reference definition label {$name}"] = static function (TestRunner $t) use ($readFirstNode, $referenceMarkdown, $slug, $name, $sourceLabel, $index): void {
        $url = '/nested-definition-' . $slug($name);
        $title = 'Nested definition ' . $name;
        [$markdown, $expectedText] = $referenceMarkdown($sourceLabel, $url, $title, $index % 3);
        $link = $readFirstNode($markdown, 'link');

        $t->same('link', $link->type);
        $t->same($url, $link->attr('url'));
        $t->same($title, $link->attr('title'));
        $t->same($expectedText, $link->children[0]->attr('text'));
    };
    $index++;
}

$imageLabels = [
    'figure-close' => ['figure \] source', 'figure ] source'],
    'diagram-close' => ['diagram \] source', 'diagram ] source'],
    'asset-close' => ['asset \] source', 'asset ] source'],
    'media-close' => ['media \] source', 'media ] source'],
    'snapshot-close' => ['snapshot \] source', 'snapshot ] source'],
    'figure-nested' => ['figure [source] packet', 'figure [source] packet'],
    'diagram-nested' => ['diagram [source] packet', 'diagram [source] packet'],
    'asset-nested' => ['asset [source] packet', 'asset [source] packet'],
    'media-nested' => ['media [source] packet', 'media [source] packet'],
    'snapshot-nested' => ['snapshot [source] packet', 'snapshot [source] packet'],
];

foreach ($imageLabels as $name => [$sourceLabel, $expectedLabel]) {
    $tests["maps upstream image reference definition label {$name}"] = static function (TestRunner $t) use ($readFirstNode, $slug, $name, $sourceLabel, $expectedLabel): void {
        $url = 'media/' . $slug($name) . '.png';
        $title = 'Image definition ' . $name;
        $markdown = "![Alt {$name}][{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"{$title}\"";
        $image = $readFirstNode($markdown, 'image');

        $t->same('image', $image->type);
        $t->same($url, $image->attr('url'));
        $t->same($title, $image->attr('title'));
        $t->same("Alt {$name}", $image->attr('alt'));
        $t->same($expectedLabel, str_replace('\]', ']', $sourceLabel));
    };
}

$wordpressCases = [
    'escaped full reference' => ['Packet', 'review \] packet', '/wp-escaped-full', 'Review &amp; packet', 'Packet'],
    'escaped shortcut reference' => ['review \] packet', 'review \] packet', '/wp-escaped-shortcut', 'Shortcut &amp; packet', 'review ] packet'],
    'nested full reference' => ['Packet', 'review [nested] packet', '/wp-nested-full', 'Nested &amp; packet', 'Packet'],
    'nested shortcut reference' => ['review [nested] packet', 'review [nested] packet', '/wp-nested-shortcut', 'Nested shortcut &amp; packet', 'review [nested] packet'],
];

foreach ($wordpressCases as $name => [$visible, $sourceLabel, $url, $title, $expectedText]) {
    $tests["maps upstream reference definition label through wordpress handoff {$name}"] = static function (TestRunner $t) use ($visible, $sourceLabel, $url, $title, $expectedText): void {
        $markdown = $visible === $sourceLabel
            ? "[{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"{$title}\""
            : "[{$visible}][{$sourceLabel}]\n\n[{$sourceLabel}]: {$url} \"{$title}\"";
        $document = (new MarkdownReader())->read($markdown);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('<a href="' . $url . '" title="' . $title . '">' . $expectedText . '</a>', $blocks);
    };
}

return $tests;
