<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Preserve compact display-formula regions before prose merging.
 *
 * PDF source streams frequently split a formula into a base, a raised glyph,
 * and an operator tail. This processor requires local geometry plus operator
 * and operand evidence; it never recognizes a particular variable, number,
 * or equation. Positioned text may supply spacing only when it contains the
 * exact same non-whitespace characters as the source records it replaces.
 */
final class PdfFormulaSemanticProcessor implements PdfSemanticRecordProcessor
{
    public const LINE_PREFIX = "\x1EPDF-FORMULA\x1F";

    private int $regionCount = 0;

    public function name(): string
    {
        return 'formula-regions';
    }

    public function regionCount(): int
    {
        return $this->regionCount;
    }

    /** @param array<string,mixed>|null $item */
    public function positionedItemLooksLikeFormula(?array $item): bool
    {
        if (!is_array($item)
            || !$this->hasGeometry($item)
            || ($item['code'] ?? false) === true) {
            return false;
        }

        $text = trim((string) ($item['text'] ?? ''));

        return $this->textLength($this->compact($text)) <= 120
            && $this->looksLikeFormula($text);
    }

    /**
     * Prove that consecutive source atoms and one visual formula are the
     * same content. The source-order span counts painted glyph runs, not
     * source text records, so it may be wider than the fragment count when a
     * superscript or operator changes font or baseline.
     *
     * @param list<array{page:int,stream:int,text:string}> $sourceItems
     * @param list<int> $indexes
     * @param array<string,mixed>|null $positionedItem
     */
    public function sourceFragmentsMatchPositionedFormula(
        array $sourceItems,
        array $indexes,
        ?array $positionedItem
    ): bool {
        if (!$this->positionedItemLooksLikeFormula($positionedItem)
            || count($indexes) < 2
            || count($indexes) > 6
            || !isset($positionedItem['sourceOrderStart'], $positionedItem['sourceOrderEnd'])) {
            return false;
        }

        $sourceOrderSpan = (int) $positionedItem['sourceOrderEnd']
            - (int) $positionedItem['sourceOrderStart'] + 1;
        if ($sourceOrderSpan < count($indexes) || $sourceOrderSpan > 12) {
            return false;
        }

        $page = null;
        $stream = null;
        $sourceText = '';
        foreach ($indexes as $index) {
            $sourceItem = $sourceItems[$index] ?? null;
            if (!is_array($sourceItem)) {
                return false;
            }
            $page ??= (int) ($sourceItem['page'] ?? 0);
            $stream ??= (int) ($sourceItem['stream'] ?? 0);
            if ((int) ($sourceItem['page'] ?? 0) !== $page
                || (int) ($sourceItem['stream'] ?? 0) !== $stream) {
                return false;
            }
            $sourceText .= (string) ($sourceItem['text'] ?? '');
        }

        return $this->compact($sourceText)
            === $this->compact((string) ($positionedItem['text'] ?? ''));
    }

    public function process(array $records): array
    {
        $this->regionCount = 0;
        $processed = [];
        for ($index = 0, $count = count($records); $index < $count;) {
            $formula = $this->formulaAt($records, $index);
            if ($formula === null) {
                $processed[] = $records[$index];
                $index++;
                continue;
            }

            $this->regionCount++;
            $layout = is_array($records[$index]['layout'] ?? null) ? $records[$index]['layout'] : [];
            $layout['sourcePdfRegionRole'] = 'formula';
            $layout['sourcePdfFormulaGroup'] = $this->regionCount;
            $layout['sourcePdfFormulaText'] = $formula['text'];
            $processed[] = ['text' => $formula['text'], 'layout' => $layout];
            $index += $formula['consumed'];
        }

        return $processed;
    }

    /**
     * @param list<array{text:string,layout:array<string,mixed>|null}> $records
     * @return array{text:string,consumed:int}|null
     */
    private function formulaAt(array $records, int $start): ?array
    {
        $firstLayout = is_array($records[$start]['layout'] ?? null) ? $records[$start]['layout'] : null;
        if (!$this->hasGeometry($firstLayout) || ($firstLayout['code'] ?? false) === true) {
            return null;
        }
        $page = (int) $firstLayout['page'];
        $sourceParts = [];
        $layouts = [];
        $maximum = min(count($records), $start + 6);
        for ($end = $start; $end < $maximum; $end++) {
            $record = $records[$end];
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $text = trim((string) ($record['text'] ?? ''));
            if ($text === ''
                || !$this->hasGeometry($layout)
                || (int) $layout['page'] !== $page
                || ($layout['code'] ?? false) === true
                || $this->textLength($text) > 64) {
                break;
            }
            $sourceParts[] = $text;
            $layouts[] = $layout;
            $consumed = $end - $start + 1;
            $sourceText = implode(' ', $sourceParts);
            $sourceCompact = $this->compact($sourceText);
            if ($sourceCompact === '' || $this->textLength($sourceCompact) > 120) {
                break;
            }

            $candidates = [$sourceText];
            foreach ($layouts as $candidateLayout) {
                $candidate = trim((string) ($candidateLayout['positionedTextCandidate'] ?? ''));
                if ($candidate !== '' && $this->compact($candidate) === $sourceCompact) {
                    array_unshift($candidates, $candidate);
                }
            }
            foreach (array_values(array_unique($candidates)) as $candidate) {
                if ($this->looksLikeFormula($candidate) && $this->geometryIsCoherent($layouts)) {
                    return ['text' => $this->normalizeFormulaSpacing($candidate), 'consumed' => $consumed];
                }
            }
        }

        return null;
    }

    private function looksLikeFormula(string $text): bool
    {
        $text = trim($text);
        if ($text === '' || str_contains($text, '://') || str_contains($text, '@')) {
            return false;
        }
        // Multiple statement separators on one compact visual line are
        // strong programming/control-flow evidence (for example a loop
        // header), not display mathematics. One semicolon remains available
        // for ordinary mathematical notation.
        $semicolonCount = substr_count($text, ';');
        if ($semicolonCount >= 2) {
            return false;
        }
        $operatorCount = preg_match_all('/[=≠≤≥<>+−×÷±∑∫√∂∞^]/u', $text, $operators);
        $operandCount = preg_match_all('/[\p{L}\p{N}]+/u', $text, $operands);
        if ($operatorCount === false || $operandCount === false || $operatorCount < 1 || $operandCount < 2) {
            return false;
        }
        // Equality/inequality is sufficient by itself. Other notation needs
        // two operators so prose such as “A + B” is not promoted eagerly.
        if (preg_match('/[=≠≤≥<>]/u', $text) !== 1 && $operatorCount < 2) {
            return false;
        }
        foreach ($operands[0] as $operand) {
            if (preg_match('/^\p{Ll}{5,}$/u', $operand) === 1) {
                return false;
            }
        }
        $forbidden = preg_match_all('/[^\p{L}\p{M}\p{N}\s=≠≤≥<>+−–—×÷±∑∫√∂∞^()\[\]{}.,:;_\-\/]/u', $text, $invalid);

        return $forbidden !== false && $forbidden <= 1;
    }

    /** @param list<array<string,mixed>> $layouts */
    private function geometryIsCoherent(array $layouts): bool
    {
        if ($layouts === []) {
            return false;
        }
        $page = (int) $layouts[0]['page'];
        $minimumX = INF;
        $maximumX = -INF;
        $minimumY = INF;
        $maximumY = -INF;
        $largestFont = 1.0;
        foreach ($layouts as $layout) {
            if ((int) $layout['page'] !== $page) {
                return false;
            }
            $minimumX = min($minimumX, (float) $layout['x1']);
            $maximumX = max($maximumX, (float) $layout['x2']);
            $minimumY = min($minimumY, (float) $layout['y1']);
            $maximumY = max($maximumY, (float) $layout['y2']);
            $largestFont = max($largestFont, (float) $layout['fontSize']);
        }

        return $maximumX - $minimumX <= max(180.0, $largestFont * 24.0)
            && $maximumY - $minimumY <= max(36.0, $largestFont * 3.5);
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

        return (int) $layout['page'] > 0;
    }

    private function compact(string $text): string
    {
        return preg_replace('/\s+/u', '', $text) ?? '';
    }

    private function normalizeFormulaSpacing(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
        $text = preg_replace('/\s*([=≠≤≥<>+−×÷±])\s*/u', ' $1 ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function textLength(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }
}
