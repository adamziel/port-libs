<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Reattach detached superscript markers to the text run they annotate.
 *
 * PDF text streams often emit a footnote marker as a separate run even when
 * it is visually attached to the preceding word. The decision here is based
 * only on relative font size and geometry: a compact run must sit immediately
 * after and above a larger baseline on the same page and content stream.
 */
final class PdfInlineMarkerSemanticProcessor implements PdfSemanticRecordProcessor
{
    private int $markerCount = 0;

    public function name(): string
    {
        return 'inline-markers';
    }

    public function markerCount(): int
    {
        return $this->markerCount;
    }

    public function process(array $records): array
    {
        $this->markerCount = 0;
        $merged = [];
        foreach ($records as $record) {
            $previousIndex = array_key_last($merged);
            if ($previousIndex === null || !$this->isDetachedInlineMarker($merged[$previousIndex], $record)) {
                $merged[] = $record;
                continue;
            }

            $marker = trim($record['text']);
            $previous = $merged[$previousIndex];
            $layout = $previous['layout'];
            $markerLayout = $record['layout'];
            $previous['text'] = rtrim($previous['text']) . $marker;
            $layout['x2'] = max((float) $layout['x2'], (float) $markerLayout['x2']);
            $layout['y2'] = max((float) $layout['y2'], (float) $markerLayout['y2']);
            if (isset($layout['sourceOrderEnd'], $markerLayout['sourceOrderEnd'])) {
                $layout['sourceOrderEnd'] = max((int) $layout['sourceOrderEnd'], (int) $markerLayout['sourceOrderEnd']);
            }
            $layout['sourcePdfInlineMarkerCount'] = (int) ($layout['sourcePdfInlineMarkerCount'] ?? 0) + 1;
            $layout['sourcePdfProtectedSemanticContent'] = true;
            $previous['layout'] = $layout;
            $merged[$previousIndex] = $previous;
            $this->markerCount++;
        }

        return array_values($merged);
    }

    /**
     * @param array{text:string,layout:array<string,mixed>|null} $base
     * @param array{text:string,layout:array<string,mixed>|null} $candidate
     */
    private function isDetachedInlineMarker(array $base, array $candidate): bool
    {
        $text = trim($candidate['text']);
        if (preg_match('/^(?:[\p{L}\p{N}*\x{2020}\x{2021}\x{00A7}\x{00B6}]{1,3}|[\[(][\p{L}\p{N}]{1,2}[\])])$/u', $text) !== 1) {
            return false;
        }
        $baseLayout = is_array($base['layout'] ?? null) ? $base['layout'] : null;
        $markerLayout = is_array($candidate['layout'] ?? null) ? $candidate['layout'] : null;
        if (!$this->hasGeometry($baseLayout) || !$this->hasGeometry($markerLayout)) {
            return false;
        }
        if ((int) $baseLayout['page'] !== (int) $markerLayout['page']) {
            return false;
        }
        if (isset($baseLayout['sourceStream'], $markerLayout['sourceStream'])
            && (int) $baseLayout['sourceStream'] !== (int) $markerLayout['sourceStream']) {
            return false;
        }
        if (($baseLayout['code'] ?? false) === true || ($markerLayout['code'] ?? false) === true) {
            return false;
        }
        foreach (['sourcePdfTableGroup', 'sourcePdfLineOrientedGroup'] as $structuralKey) {
            if (isset($baseLayout[$structuralKey]) || isset($markerLayout[$structuralKey])) {
                return false;
            }
        }
        if (($baseLayout['sourcePdfRegionRole'] ?? null) === 'formula'
            || ($markerLayout['sourcePdfRegionRole'] ?? null) === 'formula') {
            return false;
        }

        $baseText = rtrim($base['text']);
        $baseFont = max(1.0, (float) $baseLayout['fontSize']);
        $markerFont = max(1.0, (float) $markerLayout['fontSize']);
        if ($baseText === '' || $markerFont >= $baseFont * 0.84) {
            return false;
        }

        $horizontalGap = (float) $markerLayout['x1'] - (float) $baseLayout['x2'];
        if ($horizontalGap < -$baseFont * 0.60 || $horizontalGap > $baseFont * 1.10) {
            return false;
        }
        $baseCenterY = ((float) $baseLayout['y1'] + (float) $baseLayout['y2']) / 2.0;
        $markerCenterY = ((float) $markerLayout['y1'] + (float) $markerLayout['y2']) / 2.0;
        $rise = $markerCenterY - $baseCenterY;
        if ($rise < max(0.75, $baseFont * 0.10) || $rise > $baseFont * 0.90) {
            return false;
        }

        return (float) $markerLayout['y1'] <= (float) $baseLayout['y2'] + $baseFont * 0.25
            && (float) $markerLayout['y2'] >= (float) $baseLayout['y1'];
    }

    /** @param array<string,mixed>|null $layout */
    private function hasGeometry(?array $layout): bool
    {
        if (!is_array($layout)) {
            return false;
        }
        foreach (['page', 'x1', 'y1', 'x2', 'y2', 'fontSize'] as $key) {
            if (!is_int($layout[$key] ?? null) && !is_float($layout[$key] ?? null)) {
                return false;
            }
        }

        return (int) $layout['page'] > 0
            && (float) $layout['x2'] >= (float) $layout['x1']
            && (float) $layout['y2'] >= (float) $layout['y1'];
    }
}
