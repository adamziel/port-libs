<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfTextBlockConverter
{
    /**
     * Native boundary for marker.pdf.extract_text::pdftext_format_to_blocks.
     *
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    public function pdftextFormatToPage(array $page, int $pnum): array
    {
        $this->assertPdftextPage($page);
        $pageBlocks = [];
        $spanId = 0;

        foreach ($page['blocks'] as $blockIndex => $block) {
            foreach ($block['lines'] as $lineIndex => $line) {
                $spans = [];
                foreach ($line['spans'] as $spanIndex => $span) {
                    $text = $span['text'];
                    while ($text !== '' && in_array(substr($text, -1), ["\n", "\r"], true)) {
                        $text = substr($text, 0, -1);
                    }
                    $text = str_replace("-\n", '', $text);

                    $font = $span['font'];
                    $fontName = $this->fontName($font['name']);
                    $flags = array_key_exists('flags', $font) && $font['flags'] !== null ? (int) $font['flags'] : null;

                    $spanObj = [
                        'text' => $text,
                        'bbox' => $this->bbox($span['bbox'], "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].bbox"),
                        'span_id' => $pnum . '_' . $spanId,
                        'font' => $fontName . '_' . $this->fontFlagsDecomposer($flags),
                        'font_weight' => (float) $font['weight'],
                        'font_size' => (float) $font['size'],
                    ];
                    foreach (['rotation', 'char_start_idx', 'char_end_idx'] as $metadataKey) {
                        if (array_key_exists($metadataKey, $span) && (is_int($span[$metadataKey]) || is_float($span[$metadataKey]))) {
                            $spanObj[$metadataKey] = (int) $span[$metadataKey];
                        }
                    }
                    if (array_key_exists('chars', $span) && is_array($span['chars'])) {
                        $spanObj['chars'] = array_values($span['chars']);
                    }

                    $spans[] = $spanObj;
                    $spanId++;
                }

                $lineBbox = $this->bbox($line['bbox'], "blocks[{$blockIndex}].lines[{$lineIndex}].bbox");
                $lineObj = [
                    'spans' => $spans,
                    'bbox' => $lineBbox,
                ];

                if ($this->area($lineBbox) < 0.0) {
                    continue;
                }

                $pageBlocks[] = [
                    'lines' => [$lineObj],
                    'bbox' => $lineBbox,
                    'pnum' => $pnum,
                ];
            }
        }

        $pageBbox = $this->bbox($page['bbox'], 'bbox');
        $pageWidth = abs($pageBbox[2] - $pageBbox[0]);
        $pageHeight = abs($pageBbox[3] - $pageBbox[1]);
        $rotation = (int) $page['rotation'];
        if ($rotation === 90 || $rotation === 270) {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }

        return [
            'blocks' => $pageBlocks,
            'pnum' => (int) $page['page'],
            'bbox' => [0.0, 0.0, $pageWidth, $pageHeight],
            'rotation' => $rotation,
            'char_blocks' => array_values($page['blocks']),
        ];
    }

    public function fontFlagsDecomposer(?int $flags): string
    {
        if ($flags === null) {
            return '';
        }

        $descriptions = [];
        if (($flags & (1 << 0)) !== 0) {
            $descriptions[] = 'fixed_pitch';
        }
        if (($flags & (1 << 1)) !== 0) {
            $descriptions[] = 'serif';
        }
        if (($flags & (1 << 2)) !== 0) {
            $descriptions[] = 'symbolic';
        }
        if (($flags & (1 << 3)) !== 0) {
            $descriptions[] = 'script';
        }
        if (($flags & (1 << 5)) !== 0) {
            $descriptions[] = 'non_symbolic';
        }
        if (($flags & (1 << 6)) !== 0) {
            $descriptions[] = 'italic';
        }
        if (($flags & (1 << 16)) !== 0) {
            $descriptions[] = 'all_cap';
        }
        if (($flags & (1 << 17)) !== 0) {
            $descriptions[] = 'small_cap';
        }
        if (($flags & (1 << 18)) !== 0) {
            $descriptions[] = 'bold';
        }
        if (($flags & (1 << 19)) !== 0) {
            $descriptions[] = 'use_extern_attr';
        }

        return implode('_', $descriptions);
    }

    private function fontName(mixed $name): string
    {
        return $name === null ? 'None' : (string) $name;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function assertPdftextPage(array $page): void
    {
        $this->bbox($page['bbox'] ?? null, 'bbox');
        $this->assertNumeric($page['rotation'] ?? null, 'rotation');
        $this->assertNumeric($page['page'] ?? null, 'page');

        if (!isset($page['blocks']) || !is_array($page['blocks']) || !array_is_list($page['blocks'])) {
            throw new InvalidArgumentException('pdftext page blocks must be a list.');
        }

        foreach ($page['blocks'] as $blockIndex => $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException("pdftext block {$blockIndex} must be a dictionary.");
            }
            if (!isset($block['lines']) || !is_array($block['lines']) || !array_is_list($block['lines'])) {
                throw new InvalidArgumentException("pdftext block {$blockIndex} lines must be a list.");
            }

            foreach ($block['lines'] as $lineIndex => $line) {
                if (!is_array($line)) {
                    throw new InvalidArgumentException("pdftext line {$blockIndex}.{$lineIndex} must be a dictionary.");
                }
                $this->bbox($line['bbox'] ?? null, "blocks[{$blockIndex}].lines[{$lineIndex}].bbox");
                if (!isset($line['spans']) || !is_array($line['spans']) || !array_is_list($line['spans'])) {
                    throw new InvalidArgumentException("pdftext line {$blockIndex}.{$lineIndex} spans must be a list.");
                }

                foreach ($line['spans'] as $spanIndex => $span) {
                    if (!is_array($span)) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} must be a dictionary.");
                    }
                    if (!array_key_exists('text', $span) || !is_string($span['text'])) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} text must be a string.");
                    }
                    $this->bbox($span['bbox'] ?? null, "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].bbox");
                    if (!array_key_exists('font', $span) || !is_array($span['font'])) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} font must be a dictionary.");
                    }
                    if (!array_key_exists('name', $span['font']) || ($span['font']['name'] !== null && !is_string($span['font']['name']))) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} font.name must be a string or null.");
                    }
                    foreach (['weight', 'size'] as $fontKey) {
                        $this->assertNumeric($span['font'][$fontKey] ?? null, "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].font.{$fontKey}");
                    }
                    if (array_key_exists('flags', $span['font']) && $span['font']['flags'] !== null) {
                        $this->assertNumeric($span['font']['flags'], "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].font.flags");
                    }
                    foreach (['rotation', 'char_start_idx', 'char_end_idx'] as $metadataKey) {
                        if (array_key_exists($metadataKey, $span) && !is_int($span[$metadataKey]) && !is_float($span[$metadataKey])) {
                            throw new InvalidArgumentException("pdftext span {$metadataKey} must be numeric when supplied.");
                        }
                    }
                }
            }
        }
    }

    private function assertNumeric(mixed $value, string $field): void
    {
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException("pdftext {$field} must be numeric.");
        }
    }

    /**
     * @param mixed $value
     * @return list<float>
     */
    private function bbox(mixed $value, string $field): array
    {
        if (!is_array($value) || count($value) !== 4) {
            throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox.");
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part)) {
                throw new InvalidArgumentException("pdftext {$field} must be a four-number bbox.");
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }

    /**
     * @param list<float> $bbox
     */
    private function area(array $bbox): float
    {
        return ($bbox[2] - $bbox[0]) * ($bbox[3] - $bbox[1]);
    }
}
