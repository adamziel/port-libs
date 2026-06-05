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
                    if (($span['superscript'] ?? false) === true || ($span['subscript'] ?? false) === true) {
                        $text = trim($text);
                    }

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
                    if (array_key_exists('url', $span) && is_string($span['url']) && trim($span['url']) !== '') {
                        $spanObj['pdftext_url'] = $span['url'];
                        $spanObj['pdftext_url_is_safe'] = $this->isSafeUri($span['url']);
                        if ($spanObj['pdftext_url_is_safe']) {
                            $spanObj['url'] = $span['url'];
                        }
                    }
                    if (array_key_exists('rotation', $span) && (is_int($span['rotation']) || is_float($span['rotation']))) {
                        $spanObj['rotation'] = (int) $span['rotation'];
                    }
                    foreach (['char_start_idx', 'char_end_idx'] as $metadataKey) {
                        if (array_key_exists($metadataKey, $span)) {
                            $spanObj[$metadataKey] = $this->integerMetadata($span[$metadataKey], "span {$metadataKey}");
                        }
                    }
                    if (array_key_exists('chars', $span) && is_array($span['chars'])) {
                        $spanObj['chars'] = array_values($span['chars']);
                    }
                    if (($span['superscript'] ?? false) === true) {
                        $spanObj['has_superscript'] = true;
                    }
                    if (($span['subscript'] ?? false) === true) {
                        $spanObj['has_subscript'] = true;
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

        $sourceBbox = $this->bbox($page['bbox'], 'bbox');
        $pageWidth = abs($sourceBbox[2] - $sourceBbox[0]);
        $pageHeight = abs($sourceBbox[3] - $sourceBbox[1]);
        $sourcePage = $this->integerMetadata($page['page'], 'page');
        $rotation = $this->pageRotation($page['rotation']);
        if ($rotation === 90 || $rotation === 270) {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }

        return [
            'blocks' => $pageBlocks,
            'pnum' => $sourcePage,
            'bbox' => [0.0, 0.0, $pageWidth, $pageHeight],
            'rotation' => $rotation,
            'char_blocks' => array_values($page['blocks']),
            'pdftext_source' => $this->pdftextSourceMetadata($page, $sourceBbox, $rotation, $sourcePage),
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
        $this->pageRotation($page['rotation'] ?? null);
        $this->integerMetadata($page['page'] ?? null, 'page');

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
                    if (!array_key_exists('flags', $span['font'])) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} font.flags is required.");
                    }
                    foreach (['weight', 'size'] as $fontKey) {
                        $this->assertNumeric($span['font'][$fontKey] ?? null, "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].font.{$fontKey}");
                    }
                    if (array_key_exists('flags', $span['font']) && $span['font']['flags'] !== null) {
                        $this->integerMetadata($span['font']['flags'], "blocks[{$blockIndex}].lines[{$lineIndex}].spans[{$spanIndex}].font.flags");
                    }
                    if (
                        array_key_exists('rotation', $span)
                        && ((!is_int($span['rotation']) && !is_float($span['rotation'])) || !is_finite((float) $span['rotation']))
                    ) {
                        throw new InvalidArgumentException('pdftext span rotation must be numeric when supplied.');
                    }
                    foreach (['char_start_idx', 'char_end_idx'] as $metadataKey) {
                        if (array_key_exists($metadataKey, $span)) {
                            $this->integerMetadata($span[$metadataKey], "span {$metadataKey}");
                        }
                    }
                    foreach (['superscript', 'subscript'] as $scriptKey) {
                        if (array_key_exists($scriptKey, $span) && !is_bool($span[$scriptKey])) {
                            throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} {$scriptKey} must be boolean when supplied.");
                        }
                    }
                    if (array_key_exists('url', $span) && $span['url'] !== null && !is_string($span['url'])) {
                        throw new InvalidArgumentException("pdftext span {$blockIndex}.{$lineIndex}.{$spanIndex} url must be a string or null.");
                    }
                }
            }
        }
    }

    private function assertNumeric(mixed $value, string $field): void
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException("pdftext {$field} must be numeric.");
        }
    }

    /**
     * @param array<string, mixed> $page
     * @param list<float> $sourceBbox
     * @return array<string, mixed>
     */
    private function pdftextSourceMetadata(array $page, array $sourceBbox, int $rotation, int $sourcePage): array
    {
        $source = [
            'page' => $sourcePage,
            'bbox' => $sourceBbox,
            'rotation' => $rotation,
        ];

        foreach (['width', 'height'] as $field) {
            if (!array_key_exists($field, $page)) {
                continue;
            }

            $this->assertNumeric($page[$field], $field);
            $source[$field] = (float) $page[$field];
        }

        if (array_key_exists('refs', $page) && $page['refs'] !== null) {
            $source['refs'] = $this->pdftextRefs($page['refs']);
        }

        return $source;
    }

    /**
     * @param mixed $refs
     * @return list<array<string, mixed>>
     */
    private function pdftextRefs(mixed $refs): array
    {
        if (!is_array($refs) || !array_is_list($refs)) {
            throw new InvalidArgumentException('pdftext refs must be a list.');
        }

        $sanitized = [];
        foreach ($refs as $index => $ref) {
            if (!is_array($ref)) {
                throw new InvalidArgumentException("pdftext refs[{$index}] must be a dictionary.");
            }

            $row = [];
            $urlWasSupplied = array_key_exists('url', $ref);
            $refWasSupplied = array_key_exists('ref', $ref);
            if (array_key_exists('url', $ref)) {
                if (!is_string($ref['url'])) {
                    throw new InvalidArgumentException("pdftext refs[{$index}].url must be a string when supplied.");
                }
                if ($this->isSafeUri($ref['url'])) {
                    $row['url'] = $ref['url'];
                }
            }
            if (array_key_exists('page', $ref)) {
                $row['page'] = $this->integerMetadata($ref['page'], "refs[{$index}].page");
            }
            if (array_key_exists('dest_pos', $ref)) {
                $row['dest_pos'] = $this->pointMetadata($ref['dest_pos'], "refs[{$index}].dest_pos");
            }
            if (array_key_exists('dest_page', $ref)) {
                $row['dest_page'] = $this->integerMetadata($ref['dest_page'], "refs[{$index}].dest_page");
            }
            if (array_key_exists('bbox', $ref)) {
                $row['bbox'] = $this->bbox($ref['bbox'], "refs[{$index}].bbox");
            }
            if (array_key_exists('idx', $ref)) {
                $row['idx'] = $this->integerMetadata($ref['idx'], "refs[{$index}].idx");
            }
            if (array_key_exists('ref', $ref)) {
                if (!is_string($ref['ref'])) {
                    throw new InvalidArgumentException("pdftext refs[{$index}].ref must be a string when supplied.");
                }
                if (trim($ref['ref']) !== '') {
                    $row['ref'] = $ref['ref'];
                }
            }
            if (array_key_exists('coord', $ref)) {
                $row['coord'] = $this->pointMetadata($ref['coord'], "refs[{$index}].coord");
            }

            if (isset($row['page'], $row['idx'])) {
                $anchor = 'page-' . $row['page'] . '-' . $row['idx'];
                if (!$refWasSupplied && !array_key_exists('ref', $row)) {
                    $row['ref'] = $anchor;
                }
                if (!$urlWasSupplied && !array_key_exists('url', $row)) {
                    $row['url'] = '#' . $anchor;
                }
            }

            if ($row !== []) {
                $sanitized[] = $row;
            }
        }

        return $sanitized;
    }

    private function integerMetadata(mixed $value, string $field): int
    {
        $this->assertNumeric($value, $field);
        $floatValue = (float) $value;
        if (!is_finite($floatValue) || floor($floatValue) !== $floatValue) {
            throw new InvalidArgumentException("pdftext {$field} must be an integer.");
        }

        return (int) $value;
    }

    private function pageRotation(mixed $value): int
    {
        $rotation = $this->integerMetadata($value, 'rotation');
        if (!in_array($rotation, [0, 90, 180, 270], true)) {
            throw new InvalidArgumentException('pdftext rotation must be one of 0, 90, 180, or 270 degrees.');
        }

        return $rotation;
    }

    /**
     * @return list<float>
     */
    private function pointMetadata(mixed $value, string $field): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException("pdftext {$field} must be a two-number coordinate.");
        }

        $point = [];
        foreach (array_values($value) as $part) {
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
                throw new InvalidArgumentException("pdftext {$field} must be a two-number coordinate.");
            }
            $point[] = (float) $part;
        }

        return $point;
    }

    private function isSafeUri(string $uri): bool
    {
        $trimmed = trim($uri);
        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed, $match) === 1) {
            return in_array(strtolower(rtrim($match[0], ':')), ['http', 'https', 'mailto', 'ftp'], true);
        }

        return str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '/')
            || str_starts_with($trimmed, './')
            || str_starts_with($trimmed, '../');
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
            if ((!is_int($part) && !is_float($part)) || !is_finite((float) $part)) {
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
