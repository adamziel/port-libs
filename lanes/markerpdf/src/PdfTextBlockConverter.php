<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

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
        $pageBlocks = [];
        $spanId = 0;

        foreach (($page['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }

            foreach (($block['lines'] ?? []) as $line) {
                if (!is_array($line)) {
                    continue;
                }

                $spans = [];
                foreach (($line['spans'] ?? []) as $span) {
                    if (!is_array($span)) {
                        continue;
                    }

                    $text = (string) ($span['text'] ?? '');
                    while ($text !== '' && in_array(substr($text, -1), ["\n", "\r"], true)) {
                        $text = substr($text, 0, -1);
                    }
                    $text = str_replace("-\n", '', $text);

                    $font = is_array($span['font'] ?? null) ? $span['font'] : [];
                    $fontName = (string) ($font['name'] ?? '');
                    $flags = array_key_exists('flags', $font) && $font['flags'] !== null ? (int) $font['flags'] : null;

                    $spans[] = [
                        'text' => $text,
                        'bbox' => $this->bbox($span['bbox'] ?? null),
                        'span_id' => $pnum . '_' . $spanId,
                        'font' => $fontName . '_' . $this->fontFlagsDecomposer($flags),
                        'font_weight' => (float) ($font['weight'] ?? 0.0),
                        'font_size' => (float) ($font['size'] ?? 0.0),
                    ];
                    $spanId++;
                }

                $lineBbox = $this->bbox($line['bbox'] ?? null);
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

        $pageBbox = $this->bbox($page['bbox'] ?? null);
        $pageWidth = abs($pageBbox[2] - $pageBbox[0]);
        $pageHeight = abs($pageBbox[3] - $pageBbox[1]);
        $rotation = (int) ($page['rotation'] ?? 0);
        if ($rotation === 90 || $rotation === 270) {
            [$pageWidth, $pageHeight] = [$pageHeight, $pageWidth];
        }

        return [
            'blocks' => $pageBlocks,
            'pnum' => isset($page['page']) ? (int) $page['page'] : $pnum,
            'bbox' => [0.0, 0.0, $pageWidth, $pageHeight],
            'rotation' => $rotation,
            'char_blocks' => array_values(array_filter($page['blocks'] ?? [], static fn (mixed $block): bool => is_array($block))),
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

    /**
     * @param mixed $value
     * @return list<float>
     */
    private function bbox(mixed $value): array
    {
        if (!is_array($value) || count($value) !== 4) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return array_map(static fn (float|int $item): float => (float) $item, array_values($value));
    }

    /**
     * @param list<float> $bbox
     */
    private function area(array $bbox): float
    {
        return ($bbox[2] - $bbox[0]) * ($bbox[3] - $bbox[1]);
    }
}
