<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\JsonReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\MediaBag;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reports linked resource repair provenance and duplicate mime groups' => static function (TestRunner $t): void {
        $bag = new MediaBag();
        $normalizedSource = 'docs/drafts/../Review.pdf';
        $encodedSource = 'docs/review%20packet.pdf';
        $mismatchedSource = 'docs/style.PDF?download=1';
        $caseUpperSource = 'Media/Case.PDF';
        $caseLowerSource = 'media/case.pdf';
        $normalizedBytes = "%PDF normalized path\n";
        $encodedBytes = "%PDF encoded path\n";
        $mismatchedBytes = "body { color: #224; }\n";
        $caseUpperBytes = "%PDF upper case\n";
        $caseLowerBytes = "%PDF lower case\n";
        $text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
        $link = static fn (string $url, string $label): AstNode => new AstNode('link', [
            'url' => $url,
            'title' => $label,
        ], [$text($label)]);

        $document = new AstNode('document', [], [
            new AstNode('paragraph', [], [
                $link($normalizedSource, 'normalized'),
                $text(' '),
                $link($encodedSource, 'encoded'),
                $text(' '),
                $link($mismatchedSource, 'style'),
                $text(' '),
                $link($caseUpperSource, 'case upper'),
                $text(' '),
                $link($caseLowerSource, 'case lower'),
            ]),
        ]);

        $filled = $bag->fillDocument($document, [
            'docs/Review.pdf' => [
                'contents' => $normalizedBytes,
                'mimeType' => 'application/pdf',
            ],
            'docs/review packet.pdf' => [
                'contents' => $encodedBytes,
                'mimeType' => 'application/pdf',
            ],
            'docs/style.PDF' => [
                'contents' => $mismatchedBytes,
                'mimeType' => 'text/css',
            ],
            $caseUpperSource => [
                'contents' => $caseUpperBytes,
                'mimeType' => 'application/pdf',
            ],
            $caseLowerSource => [
                'contents' => $caseLowerBytes,
                'mimeType' => 'application/pdf',
            ],
        ]);
        $directoryBySource = [];
        foreach ($bag->directory() as $entry) {
            $directoryBySource[$entry['source']] = $entry;
        }

        $mismatchedPath = sha1($mismatchedBytes) . '.css';
        $caseLowerPath = 'media/case-' . substr(sha1($caseLowerSource . "\0" . sha1($caseLowerBytes)), 0, 12) . '.pdf';
        $t->same([
            'media-resource-link-loaded:' . $normalizedSource,
            'media-resource-link-loaded:' . $encodedSource,
            'media-resource-link-loaded:' . $mismatchedSource,
            'media-resource-link-loaded:' . $caseUpperSource,
            'media-resource-link-loaded:' . $caseLowerSource,
        ], $filled['diagnostics']);
        $t->same('docs/Review.pdf', $directoryBySource[$normalizedSource]['canonicalSource']);
        $t->same('docs/Review.pdf', $directoryBySource[$normalizedSource]['path']);
        $t->same('normalized-path', $directoryBySource[$normalizedSource]['pathRepairSummary']);
        $t->same('docs/review packet.pdf', $directoryBySource[$encodedSource]['path']);
        $t->same('percent-decoded-path', $directoryBySource[$encodedSource]['pathRepairSummary']);
        $t->same($mismatchedPath, $directoryBySource[$mismatchedSource]['path']);
        $t->same('url-suffix-hash-path', $directoryBySource[$mismatchedSource]['pathRepairSummary']);
        $t->same('application/pdf', $directoryBySource[$mismatchedSource]['inferredMimeType']);
        $t->same('extension-content-type-disagreement:.pdf:application/pdf=>text/css:path-extension-from-content-type', $directoryBySource[$mismatchedSource]['mimeRepairSummary']);

        $extracted = $bag->extractMedia($filled['document'], 'media');
        $mappedParagraph = $extracted['document']->children[0];
        $mappedNormalized = $mappedParagraph->children[0];
        $mappedEncoded = $mappedParagraph->children[2];
        $mappedMismatch = $mappedParagraph->children[4];
        $mappedUpper = $mappedParagraph->children[6];
        $mappedLower = $mappedParagraph->children[8];
        $entriesBySource = [];
        foreach ($extracted['entries'] as $entry) {
            $entriesBySource[$entry['source']] = $entry;
        }

        $serializedDiagnostics = implode(',', $extracted['diagnostics']);
        $t->contains('media-resource-path-collision:' . $caseLowerSource, $serializedDiagnostics);
        $t->contains('media-resource-path-casefold-collision:' . $caseLowerSource, $serializedDiagnostics);
        $t->contains('media-resource-link-mapped:' . $caseLowerSource, $serializedDiagnostics);
        $t->same('media/docs/Review.pdf', $mappedNormalized->attr('url'));
        $t->same('media/docs/review packet.pdf', $mappedEncoded->attr('url'));
        $t->same('media/' . $mismatchedPath, $mappedMismatch->attr('url'));
        $t->same('media/Media/Case.PDF', $mappedUpper->attr('url'));
        $t->same('media/' . $caseLowerPath, $mappedLower->attr('url'));
        $t->same($caseLowerPath, $entriesBySource[$caseLowerSource]['mediaPath']);
        $t->same('safe-relative-path,casefold-path-collision-disambiguated', $entriesBySource[$caseLowerSource]['extractionPathRepairSummary']);
        $t->same('application-pdf', $entriesBySource[$normalizedSource]['linkedMimeGroup']);
        $t->same(4, $entriesBySource[$normalizedSource]['linkedMimeGroupSize']);
        $t->true(!array_key_exists('linkedMimeGroup', $entriesBySource[$mismatchedSource]), 'Single CSS linked resource should not receive a duplicate MIME group');

        $normalizedAttrs = $mappedNormalized->attr('attributes');
        $encodedAttrs = $mappedEncoded->attr('attributes');
        $mismatchAttrs = $mappedMismatch->attr('attributes');
        $lowerAttrs = $mappedLower->attr('attributes');
        $t->same($normalizedSource, $normalizedAttrs['data-pandoc-media-source']);
        $t->same('docs/Review.pdf', $normalizedAttrs['data-pandoc-media-canonical-source']);
        $t->same('docs/Review.pdf', $normalizedAttrs['data-pandoc-media-source-path']);
        $t->same(sha1($normalizedSource), $normalizedAttrs['data-pandoc-media-source-sha1']);
        $t->same('normalized-path', $normalizedAttrs['data-pandoc-media-path-repair']);
        $t->same('percent-decoded-path', $encodedAttrs['data-pandoc-media-path-repair']);
        $t->same('application/pdf', $mismatchAttrs['data-pandoc-media-inferred-type']);
        $t->same('declared', $mismatchAttrs['data-pandoc-media-mime-source']);
        $t->same('extension-content-type-disagreement:.pdf:application/pdf=>text/css:path-extension-from-content-type', $mismatchAttrs['data-pandoc-media-mime-repair']);
        $t->same('application-pdf', $lowerAttrs['data-pandoc-media-linked-mime-group']);
        $t->same('4', $lowerAttrs['data-pandoc-media-linked-mime-group-size']);
        $t->same('safe-relative-path,casefold-path-collision-disambiguated', $lowerAttrs['data-pandoc-media-path-repair']);

        $markdown = (new MarkdownWriter())->write($extracted['document']);
        $blocks = (new WordPressBlockWriter())->write($extracted['document']);
        $roundTrip = (new JsonReader())->read((new JsonWriter())->write($extracted['document']));
        $t->contains('data-pandoc-media-path-repair="percent-decoded-path"', $markdown);
        $t->contains('data-pandoc-media-linked-mime-group="application-pdf"', $blocks);
        $t->same('casefold-path-collision-disambiguated', explode(',', $roundTrip->children[0]->children[8]->attr('attributes')['data-pandoc-media-path-repair'])[1]);
    },
];
