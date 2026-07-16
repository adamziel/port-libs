<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PageInspector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 12,
    'blocks' => [
        [
            'block_type' => 'Title',
            'lines' => [[
                'bbox' => [72.0, 48.0, 360.0, 68.0],
                'spans' => [
                    ['text' => 'Migration ', 'font_size' => 18.0],
                    ['text' => 'Review', 'font_size' => 18.0],
                ],
            ]],
        ],
        [
            'block_type' => 'Text',
            'lines' => [
                [
                    'bbox' => [72.0, 104.0, 520.0, 118.0],
                    'spans' => [
                        ['text' => 'Only nonblank lines and spans become review metadata.', 'font_size' => 11.0],
                    ],
                ],
                [
                    'bbox' => [72.0, 126.0, 120.0, 138.0],
                    'spans' => [
                        ['text' => '   ', 'font_size' => 11.0],
                    ],
                ],
            ],
        ],
    ],
];

$inspector = new PageInspector();
$metadata = [
    'markerpdfPage' => $page['pnum'],
    'nonblankLines' => count($inspector->getNonblankLines($page)),
    'nonblankSpans' => count($inspector->getNonblankSpans($page)),
    'fontSizes' => array_values(array_unique($inspector->getFontSizes($page))),
    'lineHeights' => array_values(array_unique($inspector->getLineHeights($page))),
];

echo '<!-- markerpdf:page-inspection ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph {\"metadata\":" . json_encode($metadata, JSON_UNESCAPED_SLASHES) . "} -->\n";
echo '<p>' . htmlspecialchars($inspector->prelimText($page), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
