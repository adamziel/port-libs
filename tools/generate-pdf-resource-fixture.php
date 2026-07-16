<?php

declare(strict_types=1);

const PORT_LIBS_PDF_RESOURCE_LINES_PER_PAGE = 20;

/** Escape one deterministic ASCII string for a PDF literal string. */
function port_libs_pdf_resource_escape_literal(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

/**
 * Return 20 visual text lines with a realistic mixture of page layouts.
 *
 * Each line has one or more independently positioned cells. A unique visible
 * marker on the first cell makes omissions, duplicate lines, and a missing
 * final page observable without relying on PDF comments or object metadata.
 *
 * @return list<array{y:int,fontSize:float,cells:list<array{x:int,text:string}>}>
 */
function port_libs_pdf_resource_page_lines(int $page): array
{
    $titles = [
        'Searchable field notes',
        'Quarterly review summary',
        'Operations reference sheet',
        'Measured observations',
        'Planning record',
        'Service overview',
    ];
    $leads = [
        'This deterministic page mixes headings, prose, labels, and measured values.',
        'The fixture keeps every line searchable while varying ordinary document structure.',
        'Stable generic wording exercises reading order without copying a source document.',
        'Independent text positions make missing lines and column-order mistakes observable.',
    ];
    $sentences = [
        'records remain easy to search',
        'reviewers compare stable values',
        'headings separate nearby topics',
        'dates and labels stay aligned',
        'short notes retain reading order',
        'the next paragraph changes focus',
        'measured totals use plain units',
        'side notes add bounded context',
        'each sentence is deterministic',
        'generic wording avoids source bias',
        'page boundaries remain explicit',
        'column groups preserve local order',
    ];

    $marker = static fn (int $line): string => sprintf('RESOURCE PAGE %03d LINE %02d', $page, $line);
    $sentence = static fn (int $line): string => $sentences[($page * 3 + $line * 5) % count($sentences)];
    $lines = [
        [
            'y' => 750,
            'fontSize' => 13.0,
            'cells' => [[
                'x' => 72,
                'text' => $marker(1) . ': ' . $titles[($page - 1) % count($titles)],
            ]],
        ],
        [
            'y' => 728,
            'fontSize' => 9.5,
            'cells' => [[
                'x' => 72,
                'text' => $marker(2) . ': ' . $leads[($page - 1) % count($leads)],
            ]],
        ],
    ];

    if ($page % 7 === 0) {
        // A main narrative column plus a narrow, independently positioned
        // sidebar. The sidebar baselines deliberately overlap some body lines.
        for ($line = 3; $line <= 16; $line++) {
            $lines[] = [
                'y' => 696 - (($line - 3) * 20),
                'fontSize' => 8.5,
                'cells' => [[
                    'x' => 72,
                    'text' => $marker($line) . ': ' . $sentence($line) . '.',
                ]],
            ];
        }
        for ($line = 17; $line <= 20; $line++) {
            $lines[] = [
                'y' => 684 - (($line - 17) * 48),
                'fontSize' => 6.5,
                'cells' => [[
                    'x' => 430,
                    'text' => $marker($line) . ': sidebar ' . ($line - 16),
                ]],
            ];
        }
    } elseif ($page % 5 === 0) {
        // Two prose columns followed by a small numeric, table-like block.
        for ($line = 3; $line <= 9; $line++) {
            $lines[] = [
                'y' => 696 - (($line - 3) * 25),
                'fontSize' => 8.0,
                'cells' => [[
                    'x' => 72,
                    'text' => $marker($line) . ': ' . $sentence($line) . '.',
                ]],
            ];
        }
        for ($line = 10; $line <= 15; $line++) {
            $lines[] = [
                'y' => 696 - (($line - 10) * 25),
                'fontSize' => 8.0,
                'cells' => [[
                    'x' => 322,
                    'text' => $marker($line) . ': ' . $sentence($line) . '.',
                ]],
            ];
        }
        $lines[] = [
            'y' => 328,
            'fontSize' => 7.5,
            'cells' => [
                ['x' => 72, 'text' => $marker(16) . ': Summary'],
                ['x' => 260, 'text' => 'Measure'],
                ['x' => 390, 'text' => 'Amount'],
                ['x' => 485, 'text' => 'Status'],
            ],
        ];
        for ($line = 17; $line <= 20; $line++) {
            $row = $line - 16;
            $lines[] = [
                'y' => 328 - ($row * 22),
                'fontSize' => 7.5,
                'cells' => [
                    ['x' => 72, 'text' => $marker($line)],
                    ['x' => 260, 'text' => sprintf('Series %02d', $row)],
                    ['x' => 390, 'text' => sprintf('Value %04d', ($page * 7) + ($row * 11))],
                    ['x' => 485, 'text' => $row % 2 === 0 ? 'Checked' : 'Stable'],
                ],
            ];
        }
    } else {
        // The common case is a conventional two-column prose page.
        for ($line = 3; $line <= 11; $line++) {
            $lines[] = [
                'y' => 696 - (($line - 3) * 27),
                'fontSize' => 8.0,
                'cells' => [[
                    'x' => 72,
                    'text' => $marker($line) . ': ' . $sentence($line) . '.',
                ]],
            ];
        }
        for ($line = 12; $line <= 20; $line++) {
            $lines[] = [
                'y' => 696 - (($line - 12) * 27),
                'fontSize' => 8.0,
                'cells' => [[
                    'x' => 322,
                    'text' => $marker($line) . ': ' . $sentence($line) . '.',
                ]],
            ];
        }
    }

    if (count($lines) !== PORT_LIBS_PDF_RESOURCE_LINES_PER_PAGE) {
        throw new LogicException('The searchable PDF fixture page lost its deterministic line inventory.');
    }

    return $lines;
}

/**
 * Generate a large, semantically dense, searchable PDF for resource gates.
 *
 * The default fixture contains 5,000 visible positioned lines across 250
 * pages. Legal stream comments only fill the remaining bytes needed to model
 * an 8–10 MiB upload; they are not the semantic workload.
 *
 * @return array{path:string,pages:int,linesPerPage:int,textLines:int,lastLineMarker:string,bytes:int,sha256:string}
 */
function port_libs_generate_searchable_pdf_resource_fixture(
    string $path,
    int $pages = 250,
    int $paddingBytesPerPage = 32768
): array {
    if ($path === '' || $pages < 1 || $pages > 2000 || $paddingBytesPerPage < 2 || $paddingBytesPerPage > 131072) {
        throw new InvalidArgumentException('Invalid searchable PDF resource fixture arguments.');
    }
    $stream = fopen($path, 'wb');
    if (!is_resource($stream)) {
        throw new RuntimeException('Could not write the searchable PDF resource fixture.');
    }
    try {
        $kids = [];
        for ($page = 1; $page <= $pages; $page++) {
            $kids[] = (string) (4 + (($page - 1) * 2)) . ' 0 R';
        }
        fwrite($stream, "%PDF-1.4\n");
        fwrite($stream, "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n");
        fwrite($stream, "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pages} >>\nendobj\n");
        fwrite($stream, "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n");
        $padding = '%' . str_repeat('p', $paddingBytesPerPage - 2) . "\n";
        for ($page = 1; $page <= $pages; $page++) {
            $pageObject = 4 + (($page - 1) * 2);
            $contentObject = $pageObject + 1;
            $commands = ['% RESOURCE-LAYOUT ' . ($page % 7 === 0 ? 'sidebar' : ($page % 5 === 0 ? 'numeric-table' : 'two-column'))];
            foreach (port_libs_pdf_resource_page_lines($page) as $line) {
                foreach ($line['cells'] as $cell) {
                    $commands[] = sprintf(
                        'BT /F1 %.1F Tf %d %d Tm (%s) Tj ET',
                        $line['fontSize'],
                        $cell['x'],
                        $line['y'],
                        port_libs_pdf_resource_escape_literal($cell['text'])
                    );
                }
            }
            $content = implode("\n", $commands) . "\n" . $padding;
            fwrite(
                $stream,
                "{$pageObject} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                    . "/Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n"
            );
            fwrite($stream, "{$contentObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n");
        }
        fwrite($stream, "trailer << /Root 1 0 R >>\n%%EOF\n");
    } finally {
        fclose($stream);
    }
    $bytes = filesize($path);
    if (!is_int($bytes)) {
        throw new RuntimeException('Could not measure the searchable PDF resource fixture.');
    }

    return [
        'path' => $path,
        'pages' => $pages,
        'linesPerPage' => PORT_LIBS_PDF_RESOURCE_LINES_PER_PAGE,
        'textLines' => $pages * PORT_LIBS_PDF_RESOURCE_LINES_PER_PAGE,
        'lastLineMarker' => sprintf(
            'RESOURCE PAGE %03d LINE %02d',
            $pages,
            PORT_LIBS_PDF_RESOURCE_LINES_PER_PAGE
        ),
        'bytes' => $bytes,
        'sha256' => hash_file('sha256', $path),
    ];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $options = getopt('', ['output:', 'pages::', 'padding-bytes-per-page::']);
    $output = isset($options['output']) ? (string) $options['output'] : '';
    if ($output === '') {
        fwrite(STDERR, "Usage: php tools/generate-pdf-resource-fixture.php --output=PATH [--pages=250] [--padding-bytes-per-page=32768]\n");
        exit(2);
    }
    try {
        $result = port_libs_generate_searchable_pdf_resource_fixture(
            $output,
            max(1, (int) ($options['pages'] ?? 250)),
            max(2, (int) ($options['padding-bytes-per-page'] ?? 32768))
        );
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
}
