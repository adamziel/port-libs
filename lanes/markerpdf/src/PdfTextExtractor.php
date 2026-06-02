<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfTextExtractor
{
    private const POSITIONED_TEXT_WORD_GAP = 12.0;
    private const SIMPLE_TEXT_ADVANCE_RATIO = 0.5;

    /**
     * @return list<string>
     */
    public function extractTextRuns(string $pdfBytes): array
    {
        $runs = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            foreach ($this->textRunsFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ) as $run) {
                if ($run !== '') {
                    $runs[] = $run;
                }
            }
        }

        return $runs;
    }

    public function extractPlainText(string $pdfBytes): string
    {
        return implode("\n", $this->extractTextLines($pdfBytes));
    }

    /**
     * Native boundary for marker.pdf.extract_text::naive_get_text.
     *
     * Upstream asks pypdfium for bounded text per page and appends a newline
     * after each page. Here page /Contents streams are the native page
     * boundary, with a stream-only fallback for lightweight fixtures.
     */
    public function naiveGetText(string $pdfBytes): string
    {
        $text = '';
        foreach ($this->extractPageTexts($pdfBytes) as $pageText) {
            $text .= $pageText . "\n";
        }

        return $text;
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_length_of_text.
     */
    public function getLengthOfText(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('Unable to read PDF text-length source: ' . $filepath);
        }

        return $this->length(trim($this->naiveGetText($bytes)));
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_text_blocks metadata.
     *
     * Upstream obtains `pdf_toc` from pypdfium's document outline adapter
     * before model execution. This reduced boundary extracts the same
     * title/level/page shape, plus trailer `/Info` strings for WordPress review
     * metadata, without running Python or external PDF tooling.
     *
     * @return array{pdf_toc: list<array{title: string, level: int, page: int}>, document_info: array<string, string>, pages: int}
     */
    public function extractOutlineMetadata(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [
                'pdf_toc' => [],
                'document_info' => [],
                'pages' => 0,
            ];
        }

        $objects = $this->pdfObjects($pdfBytes);

        return [
            'pdf_toc' => $this->pdfTocFromObjects($objects),
            'document_info' => $this->documentInfoFromPdf($pdfBytes, $objects),
            'pages' => count($this->orderedPageObjectNumbers($objects)),
        ];
    }

    /**
     * @return list<string>
     */
    public function extractPageLabels(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageCount = count($pageObjectNumbers);
        if ($pageCount === 0) {
            $pageCount = count($this->allDecodedStreams($pdfBytes, $objects));
        }

        return $this->pageLabels($objects, $pageCount);
    }

    /**
     * @return list<array{page_index: int, page_number: int, page_label: string, text: string}>
     */
    public function extractLabeledPageTexts(string $pdfBytes): array
    {
        $labels = $this->extractPageLabels($pdfBytes);
        $entries = [];
        foreach ($this->extractPageTexts($pdfBytes) as $pageIndex => $text) {
            $entries[] = [
                'page_index' => $pageIndex,
                'page_number' => $pageIndex + 1,
                'page_label' => $labels[$pageIndex] ?? (string) ($pageIndex + 1),
                'text' => $text,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function extractTextLines(string $pdfBytes): array
    {
        $lines = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            foreach ($this->textLinesFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function extractPageTexts(string $pdfBytes): array
    {
        $pages = [];
        foreach ($this->contentStreamsWithFontMaps($pdfBytes) as $entry) {
            $pages[] = implode("\n", $this->textLinesFromContentStream(
                $entry['stream'],
                $entry['fontToUnicodeMaps'],
                $entry['markedContentProperties']
            ));
        }

        return $pages;
    }

    /**
     * @return list<array{title: string, level: int, page: int}>
     * @param array<int, string> $objects
     */
    private function pdfTocFromObjects(array $objects): array
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null || preg_match('/\/Outlines\s+(\d+)\s+\d+\s+R\b/s', $catalog, $match) !== 1) {
            return [];
        }

        $outlineRootNumber = (int) $match[1];
        if (!isset($objects[$outlineRootNumber]) || preg_match('/\/First\s+(\d+)\s+\d+\s+R\b/s', $objects[$outlineRootNumber], $firstMatch) !== 1) {
            return [];
        }

        $pageIndexes = array_flip($this->orderedPageObjectNumbers($objects));

        return $this->outlineItemsFromLinkedList((int) $firstMatch[1], 1, $objects, $pageIndexes);
    }

    /**
     * @return list<array{title: string, level: int, page: int}>
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     * @param array<int, true> $seen
     */
    private function outlineItemsFromLinkedList(
        int $objectNumber,
        int $level,
        array $objects,
        array $pageIndexes,
        array $seen = []
    ): array {
        $items = [];

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            $body = $objects[$objectNumber];
            $title = $this->pdfStringValueAfterName($body, 'Title', $objects);
            $page = $this->outlinePageIndex($body, $objects, $pageIndexes);

            if ($title !== null && $title !== '' && $page !== null) {
                $items[] = [
                    'title' => $title,
                    'level' => $level,
                    'page' => $page,
                ];
            }

            if (preg_match('/\/First\s+(\d+)\s+\d+\s+R\b/s', $body, $firstMatch) === 1) {
                foreach ($this->outlineItemsFromLinkedList((int) $firstMatch[1], $level + 1, $objects, $pageIndexes, $seen) as $child) {
                    $items[] = $child;
                }
            }

            if (preg_match('/\/Next\s+(\d+)\s+\d+\s+R\b/s', $body, $nextMatch) !== 1) {
                break;
            }

            $objectNumber = (int) $nextMatch[1];
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageIndexes
     */
    private function outlinePageIndex(string $outlineBody, array $objects, array $pageIndexes): ?int
    {
        foreach (['Dest', 'D'] as $key) {
            $destination = $this->pdfArrayValueAfterName($outlineBody, $key);
            if ($destination !== null) {
                $pageObjectNumber = $this->firstObjectReference($destination);
                if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
                    return $pageIndexes[$pageObjectNumber];
                }
            }
        }

        $destinationObjectNumber = $this->objectReferenceValueAfterName($outlineBody, 'Dest');
        if ($destinationObjectNumber !== null && isset($objects[$destinationObjectNumber])) {
            $destination = $this->pdfArrayAtStart(trim($objects[$destinationObjectNumber]));
            if ($destination !== null) {
                $pageObjectNumber = $this->firstObjectReference($destination);
                if ($pageObjectNumber !== null && isset($pageIndexes[$pageObjectNumber])) {
                    return $pageIndexes[$pageObjectNumber];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function documentInfoFromPdf(string $pdfBytes, array $objects): array
    {
        if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R\b/s', $pdfBytes, $match) !== 1) {
            return [];
        }

        $infoObjectNumber = (int) $match[1];
        if (!isset($objects[$infoObjectNumber])) {
            return [];
        }

        $dictionary = $this->dictionaryObjectBody($objects[$infoObjectNumber]) ?? trim($objects[$infoObjectNumber]);
        $fields = [
            'Title' => 'title',
            'Author' => 'author',
            'Subject' => 'subject',
            'Keywords' => 'keywords',
            'Creator' => 'creator',
            'Producer' => 'producer',
            'CreationDate' => 'creation_date',
            'ModDate' => 'mod_date',
        ];

        $info = [];
        foreach ($fields as $pdfName => $key) {
            $value = $this->pdfStringValueAfterName($dictionary, $pdfName, $objects);
            if ($value !== null && $value !== '') {
                $info[$key] = $value;
            }
        }

        return $info;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if ($this->isCatalogObject($body)) {
                return $body;
            }
        }

        return null;
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>, markedContentProperties: array<string, array{actualText: string|null, altText: string|null}>}>
     */
    private function contentStreamsWithFontMaps(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        if ($pageObjectNumbers !== []) {
            $fontObjectMaps = $this->fontObjectMaps($objects);
            $pageStreams = $this->pageContentStreamsWithFontMaps($objects, $pageObjectNumbers, $fontObjectMaps);
            if ($pageStreams !== []) {
                return $pageStreams;
            }
        }

        $fontToUnicodeMaps = $this->fontToUnicodeMaps($pdfBytes);
        return array_map(
            static fn (string $stream): array => [
                'stream' => $stream,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
                'markedContentProperties' => [],
            ],
            $this->allDecodedStreams($pdfBytes, $objects)
        );
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>, markedContentProperties: array<string, array{actualText: string|null, altText: string|null}>}>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function pageContentStreamsWithFontMaps(array $objects, array $pageObjectNumbers, array $fontObjectMaps): array
    {
        $pages = [];
        $structureMcidOrderByPage = $this->structureTreeMcidOrderByPage($objects);
        $optionalContentStates = $this->optionalContentVisibilityStates($objects);
        foreach ($pageObjectNumbers as $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $streams = [];
            $optionalContentProperties = $this->pageOptionalContentPropertyVisibilityMap(
                $pageObjectNumber,
                $objects,
                $optionalContentStates
            );
            foreach ($this->pageContentObjectNumbers($objects[$pageObjectNumber]) as $contentObjectNumber) {
                if (!isset($objects[$contentObjectNumber])) {
                    continue;
                }

                if (!$this->optionalContentObjectVisible($objects[$contentObjectNumber], $objects, $optionalContentStates)) {
                    continue;
                }

                $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
                if ($decoded !== null) {
                    $streams[] = $this->filterOptionalContentMarkedBlocks($decoded, $optionalContentProperties);
                }
            }

            $pageFontToUnicodeMaps = $this->pageFontToUnicodeMaps($pageObjectNumber, $objects, $fontObjectMaps);
            $expanded = [
                'stream' => implode("\n", $streams),
                'fontToUnicodeMaps' => $pageFontToUnicodeMaps,
                'markedContentProperties' => $this->pageMarkedContentProperties($pageObjectNumber, $objects),
            ];

            if ($streams !== []) {
                $expandedForms = $this->expandFormXObjectInvocations(
                    $expanded['stream'],
                    $objects[$pageObjectNumber],
                    $objects,
                    $fontObjectMaps,
                    $expanded['fontToUnicodeMaps'],
                    $optionalContentStates
                );
                $expanded['stream'] = $expandedForms['stream'];
                $expanded['fontToUnicodeMaps'] = $expandedForms['fontToUnicodeMaps'];
            }

            $expanded['stream'] = $this->applyStructureTreeReadingOrder(
                $expanded['stream'],
                $objects[$pageObjectNumber],
                $objects,
                $structureMcidOrderByPage[$pageObjectNumber] ?? []
            );

            foreach ($this->annotationAppearanceStreamsWithFontMaps(
                $objects[$pageObjectNumber],
                $objects,
                $fontObjectMaps,
                $expanded['fontToUnicodeMaps'],
                $optionalContentStates
            ) as $appearance) {
                $expanded['stream'] = trim($expanded['stream']) === ''
                    ? $appearance['stream']
                    : $expanded['stream'] . "\n" . $appearance['stream'];
                $expanded['fontToUnicodeMaps'] = $appearance['fontToUnicodeMaps'];
            }

            if (trim($expanded['stream']) === '') {
                continue;
            }

            $pages[] = [
                'stream' => $expanded['stream'],
                'fontToUnicodeMaps' => $expanded['fontToUnicodeMaps'],
                'markedContentProperties' => $expanded['markedContentProperties'],
            ];
        }

        return $pages;
    }

    /**
     * @return array<int, list<int>>
     * @param array<int, string> $objects
     */
    private function structureTreeMcidOrderByPage(array $objects): array
    {
        $rootDictionary = $this->structureTreeRootDictionaryBody($objects);
        if ($rootDictionary === null) {
            return [];
        }

        $k = $this->pdfValueAfterName($rootDictionary, 'K');
        if ($k === null) {
            return [];
        }

        $order = [];
        $this->collectStructureMcidOrder($k, $objects, null, $order);

        foreach ($order as $pageObjectNumber => $mcids) {
            $deduped = [];
            foreach ($mcids as $mcid) {
                $deduped[$mcid] = $mcid;
            }
            $order[$pageObjectNumber] = array_values($deduped);
        }

        return $order;
    }

    /**
     * @param array<int, string> $objects
     */
    private function structureTreeRootDictionaryBody(array $objects): ?string
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return null;
        }

        $value = $this->pdfValueAfterName($catalog, 'StructTreeRoot');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<int>> $order
     * @param array<int, true> $seenObjects
     */
    private function collectStructureMcidOrder(
        string $value,
        array $objects,
        ?int $inheritedPageObjectNumber,
        array &$order,
        array $seenObjects = []
    ): void {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return;
            }

            $seenObjects[$objectNumber] = true;
            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidOrder($dictionary, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if (str_starts_with($value, '[')) {
            $arrayBody = $this->pdfArrayAtStart($value);
            if ($arrayBody === null) {
                return;
            }

            foreach ($this->pdfArrayItems($arrayBody) as $item) {
                $this->collectStructureMcidOrder($item, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            if ($dictionary !== null) {
                $this->collectStructureDictionaryMcidOrder($dictionary, $objects, $inheritedPageObjectNumber, $order, $seenObjects);
            }
            return;
        }

        if ($inheritedPageObjectNumber !== null && preg_match('/^[+-]?\d+$/', $value) === 1) {
            $mcid = (int) $value;
            if ($mcid >= 0) {
                $order[$inheritedPageObjectNumber][] = $mcid;
            }
        }
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, list<int>> $order
     * @param array<int, true> $seenObjects
     */
    private function collectStructureDictionaryMcidOrder(
        string $dictionary,
        array $objects,
        ?int $inheritedPageObjectNumber,
        array &$order,
        array $seenObjects
    ): void {
        $pageObjectNumber = $this->objectReferenceValueAfterName($dictionary, 'Pg') ?? $inheritedPageObjectNumber;
        $mcid = $this->pdfIntegerValueAfterName($dictionary, 'MCID');
        if ($pageObjectNumber !== null && $mcid !== null && $mcid >= 0) {
            $order[$pageObjectNumber][] = $mcid;
        }

        $k = $this->pdfValueAfterName($dictionary, 'K');
        if ($k !== null) {
            $this->collectStructureMcidOrder($k, $objects, $pageObjectNumber, $order, $seenObjects);
        }
    }

    /**
     * @param list<int> $mcidOrder
     * @param array<int, string> $objects
     */
    private function applyStructureTreeReadingOrder(string $stream, string $pageBody, array $objects, array $mcidOrder): string
    {
        if ($mcidOrder === []) {
            return $stream;
        }

        $segments = $this->markedContentSegmentsByMcid($stream, $pageBody, $objects);
        if ($segments === []) {
            return $stream;
        }

        $orderedSegments = [];
        foreach ($mcidOrder as $mcid) {
            if (!isset($segments[$mcid])) {
                continue;
            }

            foreach ($segments[$mcid] as $segmentTokens) {
                $segment = trim(implode(' ', $segmentTokens));
                if ($segment !== '') {
                    $orderedSegments[] = 'BT ' . $segment . ' ET';
                }
            }
        }

        return $orderedSegments === [] ? $stream : implode("\n", $orderedSegments);
    }

    /**
     * @return array<int, list<list<string>>>
     * @param array<int, string> $objects
     */
    private function markedContentSegmentsByMcid(string $stream, string $pageBody, array $objects): array
    {
        $properties = $this->markedContentPropertyDictionaries($pageBody, $objects);
        $segments = [];
        $activeSegments = [];
        $operands = [];
        $currentFontResource = null;
        $currentFontSize = null;

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $mcid = $this->markedContentMcidOperand($operands, $properties);
                $segmentIndex = null;
                if ($mcid !== null) {
                    $segments[$mcid] ??= [];
                    $segments[$mcid][] = $this->markedContentSegmentPrefix($currentFontResource, $currentFontSize);
                    $segmentIndex = count($segments[$mcid]) - 1;
                }

                $activeSegments[] = ['mcid' => $mcid, 'segmentIndex' => $segmentIndex];
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $activeSegments[] = ['mcid' => null, 'segmentIndex' => null];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                array_pop($activeSegments);
                $operands = [];
                continue;
            }

            $activeSegment = $this->activeMarkedContentSegment($activeSegments);
            if ($activeSegment !== null) {
                $segments[$activeSegment['mcid']][$activeSegment['segmentIndex']][] = $token;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $fontSize = $this->fontSizeOperand($operands);
                $currentFontSize = $fontSize === null ? $currentFontSize : $this->formatPdfNumber($fontSize);
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $segments;
    }

    /**
     * @param list<array{mcid: int|null, segmentIndex: int|null}> $activeSegments
     * @return array{mcid: int, segmentIndex: int}|null
     */
    private function activeMarkedContentSegment(array $activeSegments): ?array
    {
        for ($index = count($activeSegments) - 1; $index >= 0; $index--) {
            $segment = $activeSegments[$index];
            if ($segment['mcid'] !== null && $segment['segmentIndex'] !== null) {
                return [
                    'mcid' => $segment['mcid'],
                    'segmentIndex' => $segment['segmentIndex'],
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function markedContentSegmentPrefix(?string $fontResource, ?string $fontSize): array
    {
        if ($fontResource === null) {
            return [];
        }

        return ['/' . $fontResource, $fontSize ?? '12', 'Tf'];
    }

    /**
     * @param list<string> $operands
     * @param array<string, string> $properties
     */
    private function markedContentMcidOperand(array $operands, array $properties): ?int
    {
        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if ($operand === '') {
                continue;
            }

            if (str_starts_with($operand, '<<')) {
                $mcid = $this->markedContentMcidFromDictionaryToken($operand);
                if ($mcid !== null) {
                    return $mcid;
                }
                continue;
            }

            if (str_starts_with($operand, '/')) {
                $name = $this->decodePdfName(substr($operand, 1));
                if (isset($properties[$name])) {
                    $mcid = $this->markedContentMcidFromDictionaryToken('<<' . $properties[$name] . '>>');
                    if ($mcid !== null) {
                        return $mcid;
                    }
                }
            }
        }

        return null;
    }

    private function markedContentMcidFromDictionaryToken(string $token): ?int
    {
        $dictionary = str_starts_with($token, '<<') ? $this->readPdfDictionaryAt($token, 0) : $token;
        if ($dictionary === null || preg_match('/\/MCID\s+(\d+)/s', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function markedContentPropertyDictionaries(string $pageBody, array $objects): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($pageBody, $objects);
        if ($resourceDictionary === null) {
            return [];
        }

        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/s', $propertiesDictionary, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objectNumber = (int) $match[2];
                $dictionary = isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
                if ($dictionary !== null) {
                    $properties[$this->decodePdfName($match[1])] = $dictionary;
                }
            }
        }

        $offset = 0;
        while (preg_match('/\/([^\s\[\]()<>{}\/%]+)\s*<</s', $propertiesDictionary, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $dictionaryOffset = strpos($propertiesDictionary, '<<', $match[0][1]);
            if ($dictionaryOffset === false) {
                break;
            }

            $dictionary = $this->readPdfDictionaryAt($propertiesDictionary, $dictionaryOffset);
            $end = $this->pdfDictionaryEndOffset($propertiesDictionary, $dictionaryOffset);
            if ($dictionary === null || $end === null) {
                break;
            }

            $properties[$this->decodePdfName($match[1][0])] = $dictionary;
            $offset = $end + 1;
        }

        return $properties;
    }

    private function formatPdfNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.000001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, true> $activeFormObjectNumbers
     * @param array<int, bool> $optionalContentStates
     * @return array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}
     */
    private function expandFormXObjectInvocations(
        string $content,
        string $resourceOwnerBody,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = [],
        array $activeFormObjectNumbers = []
    ): array {
        if (!str_contains($content, 'Do')) {
            return [
                'stream' => $content,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
            ];
        }

        $xObjectMap = $this->xObjectResourceObjectNumbers($resourceOwnerBody, $objects);
        if ($xObjectMap === []) {
            return [
                'stream' => $content,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
            ];
        }

        $expanded = [];
        $expandedFontToUnicodeMaps = $fontToUnicodeMaps;
        $operands = [];
        foreach ($this->contentTokens($content) as $token) {
            if ($token === 'Do') {
                $xObjectName = $this->xObjectNameOperand($operands);
                if ($xObjectName !== null && isset($xObjectMap[$xObjectName])) {
                    $objectNumber = $xObjectMap[$xObjectName];
                    // Cyclic form resources should not turn page text extraction into
                    // an unbounded resource walk; later sibling Do calls still expand.
                    if (isset($activeFormObjectNumbers[$objectNumber])) {
                        $operands = [];
                        continue;
                    }

                    if (!$this->optionalContentObjectVisible($objects[$objectNumber], $objects, $optionalContentStates)) {
                        $operands = [];
                        continue;
                    }

                    $form = $this->decodedFormXObject($objects, $objectNumber);
                    if ($form !== null) {
                        $nextActiveForms = $activeFormObjectNumbers;
                        $nextActiveForms[$objectNumber] = true;
                        $formFontMaps = $this->fontResourceMapsForResourceOwnerBody($form['body'], $objects, $fontObjectMaps);
                        $fontAliases = [];
                        foreach ($formFontMaps as $name => $map) {
                            $alias = $this->formFontResourceAlias($objectNumber, $name);
                            $fontAliases[$name] = $alias;
                            $expandedFontToUnicodeMaps[$alias] = $map;
                        }

                        $formStream = $this->filterOptionalContentMarkedBlocks(
                            $form['stream'],
                            $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
                                $form['body'],
                                $objects,
                                $optionalContentStates
                            )
                        );
                        $expandedForm = $this->expandFormXObjectInvocations(
                            $this->rewriteFontResourceOperands($formStream, $fontAliases),
                            $form['body'],
                            $objects,
                            $fontObjectMaps,
                            $expandedFontToUnicodeMaps,
                            $optionalContentStates,
                            $nextActiveForms
                        );
                        $expanded[] = $expandedForm['stream'];
                        $expandedFontToUnicodeMaps = $expandedForm['fontToUnicodeMaps'];
                    }
                    $operands = [];
                    continue;
                }

                $expanded[] = $token;
                $operands = [];
                continue;
            }

            $expanded[] = $token;
            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return [
            'stream' => implode(' ', array_values(array_filter($expanded, static fn (string $segment): bool => trim($segment) !== ''))),
            'fontToUnicodeMaps' => $expandedFontToUnicodeMaps,
        ];
    }

    /**
     * @return array<string, int>
     * @param array<int, string> $objects
     */
    private function xObjectResourceObjectNumbers(string $resourceOwnerBody, array $objects): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;
        $xObjectDictionary = $this->xObjectResourceDictionaryBody($resourceDictionary, $objects);
        if ($xObjectDictionary === null) {
            return [];
        }

        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $xObjectDictionary, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $resourceObjects = [];
        foreach ($matches as $resource) {
            $resourceObjects[$this->decodePdfName($resource[1])] = (int) $resource[2];
        }

        return $resourceObjects;
    }

    /**
     * @param array<int, string> $objects
     */
    private function xObjectResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        if (!preg_match('/\/XObject\s*(?:(\d+)\s+\d+\s+R|<<)/s', $resourceDictionary, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        $offset = strpos($resourceDictionary, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($resourceDictionary, $offset);
    }

    /**
     * @return array{body: string, stream: string}|null
     * @param array<int, string> $objects
     */
    private function decodedFormXObject(array $objects, int $objectNumber): ?array
    {
        if (!isset($objects[$objectNumber]) || preg_match('/\/Subtype\s*\/Form\b/', $objects[$objectNumber]) !== 1) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objects[$objectNumber], $objects);
        if ($decoded === null) {
            return null;
        }

        return [
            'body' => $objects[$objectNumber],
            'stream' => $decoded,
        ];
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function fontResourceMapsForResourceOwnerBody(string $resourceOwnerBody, array $objects, array $fontObjectMaps): array
    {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;

        return $this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps);
    }

    /**
     * @return array<string, array{actualText: string|null, altText: string|null}>
     * @param array<int, string> $objects
     */
    private function pageMarkedContentProperties(int $pageObjectNumber, array $objects): array
    {
        $properties = [];
        foreach (array_reverse($this->pageObjectLineage($pageObjectNumber, $objects)) as $objectNumber) {
            $resourceDictionary = $this->resourceDictionaryBody($objects[$objectNumber], $objects);
            if ($resourceDictionary === null) {
                continue;
            }

            foreach ($this->markedContentPropertiesFromResourceDictionary($resourceDictionary, $objects) as $name => $property) {
                $properties[$name] = $property;
            }
        }

        return $properties;
    }

    /**
     * @param array<string, string> $fontAliases
     */
    private function rewriteFontResourceOperands(string $content, array $fontAliases): string
    {
        if ($fontAliases === []) {
            return $content;
        }

        $rewritten = [];
        $operands = [];
        foreach ($this->contentTokens($content) as $token) {
            if ($this->isOperator($token)) {
                if ($token === 'Tf' && count($operands) >= 2) {
                    $fontOperandIndex = count($operands) - 2;
                    $fontOperand = $operands[$fontOperandIndex];
                    if (str_starts_with($fontOperand, '/')) {
                        $fontName = $this->decodePdfName(substr($fontOperand, 1));
                        if (isset($fontAliases[$fontName])) {
                            $operands[$fontOperandIndex] = '/' . $fontAliases[$fontName];
                        }
                    }
                }

                foreach ($operands as $operand) {
                    $rewritten[] = $operand;
                }
                $rewritten[] = $token;
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        foreach ($operands as $operand) {
            $rewritten[] = $operand;
        }

        return implode(' ', $rewritten);
    }

    private function formFontResourceAlias(int $formObjectNumber, string $resourceName): string
    {
        return 'Fm' . $formObjectNumber . '_' . bin2hex($resourceName);
    }

    private function appearanceFontResourceAlias(int $appearanceObjectNumber, string $resourceName): string
    {
        return 'Ap' . $appearanceObjectNumber . '_' . bin2hex($resourceName);
    }

    /**
     * @return list<array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, bool> $optionalContentStates
     */
    private function annotationAppearanceStreamsWithFontMaps(
        string $pageBody,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = []
    ): array {
        $appearances = [];
        $currentFontToUnicodeMaps = $fontToUnicodeMaps;

        foreach ($this->annotationBodiesForPage($pageBody, $objects) as $annotation) {
            if (!$this->optionalContentObjectVisible($annotation['body'], $objects, $optionalContentStates)) {
                continue;
            }

            $appearanceObjectNumber = $this->normalAppearanceObjectNumber($annotation['body'], $objects);
            if ($appearanceObjectNumber === null) {
                continue;
            }

            $appearance = $this->decodedAppearanceStreamWithFontMaps(
                $appearanceObjectNumber,
                $objects,
                $fontObjectMaps,
                $currentFontToUnicodeMaps,
                $optionalContentStates
            );
            if ($appearance === null) {
                continue;
            }

            $appearances[] = $appearance;
            $currentFontToUnicodeMaps = $appearance['fontToUnicodeMaps'];
        }

        return $appearances;
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesForPage(string $pageBody, array $objects): array
    {
        $annots = $this->pdfValueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationBodiesFromValue($annots, $objects);
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                return $this->annotationBodiesFromArray($this->pdfArrayAtStart($objectBody), $objects);
            }

            $dictionary = $this->dictionaryObjectBody($objectBody);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
        }

        if (str_starts_with($value, '[')) {
            return $this->annotationBodiesFromArray($this->pdfArrayAtStart($value), $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        return [];
    }

    /**
     * @return list<array{body: string, object: int|null}>
     * @param array<int, string> $objects
     */
    private function annotationBodiesFromArray(?string $arrayBody, array $objects): array
    {
        if ($arrayBody === null) {
            return [];
        }

        $annotations = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            if (!isset($objects[$objectNumber])) {
                continue;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            if ($dictionary !== null) {
                $annotations[] = ['body' => $dictionary, 'object' => $objectNumber];
            }
        }

        foreach ($this->directDictionaries($arrayBody) as $dictionary) {
            $annotations[] = ['body' => $dictionary, 'object' => null];
        }

        return $annotations;
    }

    /**
     * @return list<string>
     */
    private function directDictionaries(string $value): array
    {
        $dictionaries = [];
        $offset = 0;
        while (($start = strpos($value, '<<', $offset)) !== false) {
            $dictionary = $this->readPdfDictionaryAt($value, $start);
            $end = $this->pdfDictionaryEndOffset($value, $start);
            if ($dictionary === null || $end === null) {
                break;
            }

            $dictionaries[] = $dictionary;
            $offset = $end + 1;
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     */
    private function normalAppearanceObjectNumber(string $annotationBody, array $objects): ?int
    {
        $appearanceDictionary = $this->appearanceDictionaryBody($annotationBody, $objects);
        if ($appearanceDictionary === null) {
            return null;
        }

        $normalAppearance = $this->pdfValueAfterName($appearanceDictionary, 'N');
        if ($normalAppearance === null) {
            return null;
        }

        $appearanceState = $this->pdfNameValueAfterName($annotationBody, 'AS');
        $normalAppearance = trim($normalAppearance);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $normalAppearance, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return null;
            }

            if ($this->isStreamObject($objects[$objectNumber])) {
                return $objectNumber;
            }

            $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
            return $dictionary === null ? null : $this->appearanceObjectNumberFromStateDictionary($dictionary, $appearanceState);
        }

        if (str_starts_with($normalAppearance, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($normalAppearance, 0);
            return $dictionary === null ? null : $this->appearanceObjectNumberFromStateDictionary($dictionary, $appearanceState);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function appearanceDictionaryBody(string $annotationBody, array $objects): ?string
    {
        $appearance = $this->pdfValueAfterName($annotationBody, 'AP');
        if ($appearance === null) {
            return null;
        }

        $appearance = trim($appearance);
        if (str_starts_with($appearance, '<<')) {
            return $this->readPdfDictionaryAt($appearance, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $appearance, $match) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        return null;
    }

    private function appearanceObjectNumberFromStateDictionary(string $dictionary, ?string $appearanceState): ?int
    {
        if ($appearanceState !== null) {
            return $this->objectReferenceValueAfterName($dictionary, $appearanceState);
        }

        $fallback = null;
        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $dictionary, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $state = $this->decodePdfName($match[1]);
            $objectNumber = (int) $match[2];
            if ($fallback === null) {
                $fallback = $objectNumber;
            }
            if ($state !== 'Off') {
                return $objectNumber;
            }
        }

        return $fallback;
    }

    /**
     * @return array{stream: string, fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>}|null
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<int, bool> $optionalContentStates
     */
    private function decodedAppearanceStreamWithFontMaps(
        int $appearanceObjectNumber,
        array $objects,
        array $fontObjectMaps,
        array $fontToUnicodeMaps,
        array $optionalContentStates = []
    ): ?array {
        if (!isset($objects[$appearanceObjectNumber]) || preg_match('/\/Subtype\s*\/Form\b/', $objects[$appearanceObjectNumber]) !== 1) {
            return null;
        }

        if (!$this->optionalContentObjectVisible($objects[$appearanceObjectNumber], $objects, $optionalContentStates)) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objects[$appearanceObjectNumber], $objects);
        if ($decoded === null) {
            return null;
        }

        $expandedFontToUnicodeMaps = $fontToUnicodeMaps;
        $fontAliases = [];
        foreach ($this->fontResourceMapsForResourceOwnerBody($objects[$appearanceObjectNumber], $objects, $fontObjectMaps) as $name => $map) {
            $alias = $this->appearanceFontResourceAlias($appearanceObjectNumber, $name);
            $fontAliases[$name] = $alias;
            $expandedFontToUnicodeMaps[$alias] = $map;
        }

        $decoded = $this->filterOptionalContentMarkedBlocks(
            $decoded,
            $this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
                $objects[$appearanceObjectNumber],
                $objects,
                $optionalContentStates
            )
        );

        return $this->expandFormXObjectInvocations(
            $this->rewriteFontResourceOperands($decoded, $fontAliases),
            $objects[$appearanceObjectNumber],
            $objects,
            $fontObjectMaps,
            $expandedFontToUnicodeMaps,
            $optionalContentStates
        );
    }

    /**
     * Native boundary for PDFium-style default-view optional content checks.
     *
     * @return array<int, bool>
     * @param array<int, string> $objects
     */
    private function optionalContentVisibilityStates(array $objects): array
    {
        $ocProperties = $this->optionalContentPropertiesDictionaryBody($objects);
        if ($ocProperties === null) {
            return [];
        }

        $ocgArray = $this->pdfArrayValueAfterNameResolved($ocProperties, 'OCGs', $objects);
        if ($ocgArray === null) {
            return [];
        }

        $states = [];
        foreach ($this->objectReferences($ocgArray) as $objectNumber) {
            $states[$objectNumber] = true;
        }

        if ($states === []) {
            return [];
        }

        $defaultConfig = $this->pdfDictionaryValueAfterNameResolved($ocProperties, 'D', $objects);
        if ($defaultConfig === null) {
            return $states;
        }

        $baseState = $this->pdfNameValueAfterName($defaultConfig, 'BaseState') ?? 'ON';
        $baseVisible = $baseState !== 'OFF';
        foreach (array_keys($states) as $objectNumber) {
            $states[$objectNumber] = $baseVisible;
        }

        foreach ($this->optionalContentObjectNumbersAfterName($defaultConfig, 'ON', $objects) as $objectNumber) {
            if (array_key_exists($objectNumber, $states)) {
                $states[$objectNumber] = true;
            }
        }

        foreach ($this->optionalContentObjectNumbersAfterName($defaultConfig, 'OFF', $objects) as $objectNumber) {
            if (array_key_exists($objectNumber, $states)) {
                $states[$objectNumber] = false;
            }
        }

        return $states;
    }

    /**
     * @param array<int, string> $objects
     */
    private function optionalContentPropertiesDictionaryBody(array $objects): ?string
    {
        $catalog = $this->catalogObjectBody($objects);
        if ($catalog === null) {
            return null;
        }

        $value = $this->pdfValueAfterName($catalog, 'OCProperties');
        return $value === null ? null : $this->pdfDictionaryFromValue($value, $objects);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function optionalContentObjectNumbersAfterName(string $dictionary, string $name, array $objects): array
    {
        $arrayBody = $this->pdfArrayValueAfterNameResolved($dictionary, $name, $objects);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    /**
     * @return array<string, bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function pageOptionalContentPropertyVisibilityMap(
        int $pageObjectNumber,
        array $objects,
        array $optionalContentStates
    ): array {
        $properties = [];
        foreach (array_reverse($this->pageObjectLineage($pageObjectNumber, $objects)) as $objectNumber) {
            foreach ($this->optionalContentPropertyVisibilityMapForResourceOwnerBody(
                $objects[$objectNumber],
                $objects,
                $optionalContentStates
            ) as $name => $visible) {
                $properties[$name] = $visible;
            }
        }

        return $properties;
    }

    /**
     * @return array<string, bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentPropertyVisibilityMapForResourceOwnerBody(
        string $resourceOwnerBody,
        array $objects,
        array $optionalContentStates
    ): array {
        $resourceDictionary = $this->resourceDictionaryBody($resourceOwnerBody, $objects) ?? $resourceOwnerBody;
        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        $offset = 0;
        while (preg_match('/\/([^\s\[\]()<>{}\/%]+)/s', $propertiesDictionary, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $name = $this->decodePdfName($match[1][0]);
            $valueOffset = $this->skipPdfWhitespace($propertiesDictionary, $match[0][1] + strlen($match[0][0]));
            if ($valueOffset >= strlen($propertiesDictionary)) {
                break;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $propertiesDictionary, $referenceMatch, 0, $valueOffset) === 1) {
                $properties[$name] = $this->optionalContentReferenceVisible(
                    (int) $referenceMatch[1],
                    $objects,
                    $optionalContentStates
                );
                $offset = $valueOffset + strlen($referenceMatch[0]);
                continue;
            }

            if (substr($propertiesDictionary, $valueOffset, 2) === '<<') {
                $dictionaryOffset = $valueOffset;
                $dictionary = $this->readPdfDictionaryTokenAt($propertiesDictionary, $dictionaryOffset);
                if ($dictionary !== null) {
                    $properties[$name] = $this->optionalContentDictionaryVisible(
                        $dictionary,
                        $objects,
                        $optionalContentStates
                    );
                    $offset = $dictionaryOffset;
                    continue;
                }
            }

            $offset = $valueOffset + 1;
        }

        return $properties;
    }

    /**
     * @param array<string, bool> $propertyVisibility
     */
    private function filterOptionalContentMarkedBlocks(string $content, array $propertyVisibility): string
    {
        if ($propertyVisibility === [] || !str_contains($content, 'BDC')) {
            return $content;
        }

        $filtered = [];
        $operands = [];
        $hiddenDepth = 0;

        foreach ($this->contentTokens($content) as $token) {
            if ($token === 'BDC') {
                $hidden = $hiddenDepth > 0 || $this->markedOptionalContentIsHidden($operands, $propertyVisibility);
                if ($hidden) {
                    $hiddenDepth++;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                if ($hiddenDepth > 0) {
                    $hiddenDepth++;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                if ($hiddenDepth > 0) {
                    $hiddenDepth--;
                    $operands = [];
                    continue;
                }

                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            if ($hiddenDepth > 0) {
                if ($this->isOperator($token)) {
                    $operands = [];
                    continue;
                }

                $operands[] = $token;
                continue;
            }

            if ($this->isOperator($token)) {
                foreach ($operands as $operand) {
                    $filtered[] = $operand;
                }
                $filtered[] = $token;
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        if ($hiddenDepth === 0) {
            foreach ($operands as $operand) {
                $filtered[] = $operand;
            }
        }

        return implode(' ', $filtered);
    }

    /**
     * @param list<string> $operands
     * @param array<string, bool> $propertyVisibility
     */
    private function markedOptionalContentIsHidden(array $operands, array $propertyVisibility): bool
    {
        if (count($operands) < 2) {
            return false;
        }

        $tagOperand = $operands[count($operands) - 2];
        $propertyOperand = $operands[count($operands) - 1];
        if (!str_starts_with($tagOperand, '/') || $this->decodePdfName(substr($tagOperand, 1)) !== 'OC') {
            return false;
        }

        if (!str_starts_with($propertyOperand, '/')) {
            return false;
        }

        $propertyName = $this->decodePdfName(substr($propertyOperand, 1));
        return isset($propertyVisibility[$propertyName]) && !$propertyVisibility[$propertyName];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentObjectVisible(string $objectBody, array $objects, array $optionalContentStates): bool
    {
        $trimmed = trim($objectBody);
        $dictionary = str_starts_with($trimmed, '<<')
            ? ($this->dictionaryObjectBody($objectBody) ?? $objectBody)
            : $objectBody;
        $optionalContent = $this->pdfValueAfterName($dictionary, 'OC');
        return $optionalContent === null
            || $this->optionalContentValueVisible($optionalContent, $objects, $optionalContentStates);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentValueVisible(string $value, array $objects, array $optionalContentStates): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            return $this->optionalContentReferenceVisible((int) $match[1], $objects, $optionalContentStates);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null
                || $this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates);
        }

        if (str_starts_with($value, '[')) {
            $values = $this->optionalContentVisibilityValuesFromValue($value, $objects, $optionalContentStates);
            return $values === [] || in_array(true, $values, true);
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentReferenceVisible(int $objectNumber, array $objects, array $optionalContentStates): bool
    {
        if (!isset($objects[$objectNumber])) {
            return true;
        }

        $dictionary = $this->dictionaryObjectBody($objects[$objectNumber]);
        if ($dictionary === null) {
            return true;
        }

        return $this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates, $objectNumber);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentDictionaryVisible(
        string $dictionary,
        array $objects,
        array $optionalContentStates,
        ?int $objectNumber = null
    ): bool {
        $type = $this->pdfNameValueAfterName($dictionary, 'Type') ?? 'OCG';
        if ($type === 'OCMD') {
            return $this->optionalContentMembershipVisible($dictionary, $objects, $optionalContentStates);
        }

        if ($type !== 'OCG') {
            return true;
        }

        $usage = $this->pdfDictionaryValueAfterNameResolved($dictionary, 'Usage', $objects);
        $view = $usage === null ? null : $this->pdfDictionaryValueAfterNameResolved($usage, 'View', $objects);
        $viewState = $view === null ? null : $this->pdfNameValueAfterName($view, 'ViewState');
        if ($viewState === 'OFF') {
            return false;
        }
        if ($viewState === 'ON') {
            return true;
        }

        return $objectNumber === null || !array_key_exists($objectNumber, $optionalContentStates)
            ? true
            : $optionalContentStates[$objectNumber];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentMembershipVisible(
        string $dictionary,
        array $objects,
        array $optionalContentStates
    ): bool {
        $ocgs = $this->pdfValueAfterName($dictionary, 'OCGs');
        if ($ocgs === null) {
            return true;
        }

        $values = $this->optionalContentVisibilityValuesFromValue($ocgs, $objects, $optionalContentStates);
        if ($values === []) {
            return true;
        }

        $policy = $this->pdfNameValueAfterName($dictionary, 'P') ?? 'AnyOn';
        return match ($policy) {
            'AllOn' => !in_array(false, $values, true),
            'AnyOff' => in_array(false, $values, true),
            'AllOff' => !in_array(true, $values, true),
            default => in_array(true, $values, true),
        };
    }

    /**
     * @return list<bool>
     * @param array<int, string> $objects
     * @param array<int, bool> $optionalContentStates
     */
    private function optionalContentVisibilityValuesFromValue(
        string $value,
        array $objects,
        array $optionalContentStates
    ): array {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) === 1) {
            return [$this->optionalContentReferenceVisible((int) $match[1], $objects, $optionalContentStates)];
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [$this->optionalContentDictionaryVisible($dictionary, $objects, $optionalContentStates)];
        }

        $arrayBody = $this->pdfArrayFromValue($value, $objects);
        if ($arrayBody === null) {
            return [];
        }

        $values = [];
        foreach ($this->objectReferences($arrayBody) as $objectNumber) {
            $values[] = $this->optionalContentReferenceVisible($objectNumber, $objects, $optionalContentStates);
        }

        return $values;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayValueAfterNameResolved(string $body, string $name, array $objects): ?string
    {
        $value = $this->pdfValueAfterName($body, $name);
        return $value === null ? null : $this->pdfArrayFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfDictionaryValueAfterNameResolved(string $body, string $name, array $objects): ?string
    {
        $value = $this->pdfValueAfterName($body, $name);
        return $value === null ? null : $this->pdfDictionaryFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfArrayFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '[')) {
            return $this->pdfArrayAtStart($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        return isset($objects[$objectNumber]) ? $this->pdfArrayAtStart(trim($objects[$objectNumber])) : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfDictionaryFromValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/s', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
    }

    private function isStreamObject(string $objectBody): bool
    {
        return preg_match('/\bstream\r?\n?/s', $objectBody) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function xObjectNameOperand(array $operands): ?string
    {
        $operand = end($operands);
        if (!is_string($operand) || !str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function allDecodedStreams(string $pdfBytes, array $objects): array
    {
        $streams = [];
        if (!preg_match_all('/<<(.*?)>>\s*stream(?:\r\n|\r|\n)?/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $streams;
        }

        foreach ($matches as $match) {
            $dict = $match[1][0];
            if ($this->isImageStreamDictionary($dict, $objects) || $this->isEmbeddedFileStreamDictionary($dict)) {
                continue;
            }

            $streamStart = $match[0][1] + strlen($match[0][0]);
            $stream = $this->streamPayloadAt($pdfBytes, $streamStart, $dict, $objects);
            if ($stream === null) {
                continue;
            }

            $decoded = $this->decodeStream($dict, $stream, $objects);
            if ($decoded === null) {
                continue;
            }
            $streams[] = $decoded;
        }

        return $streams;
    }

    /**
     * @param array<int, string> $objects
     */
    private function isImageStreamDictionary(string $dict, array $objects): bool
    {
        if (preg_match('/\/Subtype\s*\/Image\b/', $dict) === 1) {
            return true;
        }

        if (!$this->hasPdfNumberishName($dict, 'Width') || !$this->hasPdfNumberishName($dict, 'Height')) {
            return false;
        }

        $hasBitsPerComponent = $this->hasPdfNumberishName($dict, 'BitsPerComponent')
            || $this->hasPdfNumberishName($dict, 'BPC');
        if (!$hasBitsPerComponent && preg_match('/\/ImageMask\s+true\b/', $dict) !== 1) {
            return false;
        }

        return $this->imageColorSpaceFamily($dict, $objects) !== null
            || preg_match('/\/ImageMask\s+true\b/', $dict) === 1;
    }

    private function hasPdfNumberishName(string $dict, string $name): bool
    {
        $offset = $this->nameValueOffset($dict, $name);
        if ($offset === null) {
            return false;
        }

        return preg_match('/\G(?:[+-]?(?:\d+(?:\.\d*)?|\.\d+)|\d+\s+\d+\s+R\b)/s', $dict, $match, 0, $offset) === 1;
    }

    /**
     * @param array<int, string> $objects
     */
    private function imageColorSpaceFamily(string $dict, array $objects): ?string
    {
        foreach (['ColorSpace', 'CS'] as $name) {
            $offset = $this->nameValueOffset($dict, $name);
            if ($offset === null) {
                continue;
            }

            $family = $this->colorSpaceFamilyAt($dict, $offset, $objects);
            if ($family !== null) {
                return $family;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function colorSpaceFamilyAt(string $value, int $offset, array $objects, array $seenObjects = []): ?string
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return null;
        }

        if (preg_match('/\G\/([^\s\[\]()<>{}\/%]+)/s', $value, $match, 0, $offset) === 1) {
            return $this->recognizedImageColorSpace($this->decodePdfName($match[1]));
        }

        if ($value[$offset] === '[') {
            $arrayBody = $this->readPdfArrayAt($value, $offset);
            if ($arrayBody === null) {
                return null;
            }

            return $this->colorSpaceFamilyAt($arrayBody, 0, $objects, $seenObjects);
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
                return null;
            }

            $seenObjects[$objectNumber] = true;
            return $this->colorSpaceFamilyAt(trim($objects[$objectNumber]), 0, $objects, $seenObjects);
        }

        return null;
    }

    private function recognizedImageColorSpace(string $name): ?string
    {
        return match ($name) {
            'DeviceGray', 'DeviceRGB', 'DeviceCMYK',
            'G', 'RGB', 'CMYK',
            'Indexed', 'I',
            'ICCBased' => $name,
            default => null,
        };
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $objectNumber => $body) {
            if (!$this->isCatalogObject($body) || !preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match)) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->isPageObject($body)) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * Native boundary for PDF catalog /PageLabels metadata.
     *
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function pageLabels(array $objects, int $pageCount): array
    {
        if ($pageCount <= 0) {
            return [];
        }

        $fallback = [];
        for ($index = 0; $index < $pageCount; $index++) {
            $fallback[] = (string) ($index + 1);
        }

        $dictionary = $this->pageLabelsDictionaryBody($objects);
        if ($dictionary === null) {
            return $fallback;
        }

        $sections = $this->pageLabelNumberTreeEntries($dictionary, $objects);
        if ($sections === []) {
            return $fallback;
        }

        ksort($sections, SORT_NUMERIC);
        $labels = [];
        $activeSection = ['prefix' => '', 'style' => 'D', 'start' => 1];
        $activeIndex = 0;
        $sectionOffset = 0;
        $sectionIndexes = array_keys($sections);
        $sectionCount = count($sectionIndexes);

        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            while (
                $sectionOffset < $sectionCount
                && $sectionIndexes[$sectionOffset] <= $pageIndex
            ) {
                $activeIndex = (int) $sectionIndexes[$sectionOffset];
                $activeSection = $sections[$activeIndex];
                $sectionOffset++;
            }

            $number = $activeSection['start'] + ($pageIndex - $activeIndex);
            $label = $activeSection['prefix'];
            if ($activeSection['style'] !== null) {
                $label .= $this->formatPageLabelNumber($number, $activeSection['style']);
            }

            $labels[] = $label !== '' ? $label : (string) ($pageIndex + 1);
        }

        return $labels;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelsDictionaryBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if (!$this->isCatalogObject($body)) {
                continue;
            }

            if (preg_match('/\/PageLabels\s+(\d+)\s+\d+\s+R\b/s', $body, $match) === 1) {
                $objectNumber = (int) $match[1];
                return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
            }

            if (preg_match('/\/PageLabels\s*<</s', $body, $match, PREG_OFFSET_CAPTURE) === 1) {
                $offset = strpos($body, '<<', $match[0][1]);
                return $offset === false ? null : $this->readPdfDictionaryAt($body, $offset);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{prefix: string, style: string|null, start: int}>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageLabelNumberTreeEntries(string $dictionary, array $objects, array $seen = []): array
    {
        $entries = $this->pageLabelNumsEntries($dictionary, $objects);

        foreach ($this->pageLabelKidObjectNumbers($dictionary) as $kidObjectNumber) {
            if (isset($seen[$kidObjectNumber]) || !isset($objects[$kidObjectNumber])) {
                continue;
            }

            $kidDictionary = $this->dictionaryObjectBody($objects[$kidObjectNumber]);
            if ($kidDictionary === null) {
                continue;
            }

            $nextSeen = $seen;
            $nextSeen[$kidObjectNumber] = true;
            foreach ($this->pageLabelNumberTreeEntries($kidDictionary, $objects, $nextSeen) as $pageIndex => $section) {
                $entries[$pageIndex] = $section;
            }
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function pageLabelKidObjectNumbers(string $dictionary): array
    {
        if (preg_match('/\/Kids\s*\[/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $offset = strpos($dictionary, '[', $match[0][1]);
        $arrayBody = $offset === false ? null : $this->readPdfArrayAt($dictionary, $offset);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    /**
     * @return array<int, array{prefix: string, style: string|null, start: int}>
     * @param array<int, string> $objects
     */
    private function pageLabelNumsEntries(string $dictionary, array $objects): array
    {
        if (preg_match('/\/Nums\s*\[/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        $offset = strpos($dictionary, '[', $match[0][1]);
        $arrayBody = $offset === false ? null : $this->readPdfArrayAt($dictionary, $offset);
        if ($arrayBody === null) {
            return [];
        }

        $entries = [];
        $index = 0;
        $length = strlen($arrayBody);
        while ($index < $length) {
            $index = $this->skipPdfWhitespace($arrayBody, $index);
            if (preg_match('/[+-]?\d+/A', substr($arrayBody, $index), $pageMatch) !== 1) {
                $index++;
                continue;
            }

            $pageIndex = max(0, (int) $pageMatch[0]);
            $index += strlen($pageMatch[0]);
            $index = $this->skipPdfWhitespace($arrayBody, $index);

            $labelDictionary = $this->pageLabelValueDictionary($arrayBody, $index, $objects);
            if ($labelDictionary === null) {
                $index++;
                continue;
            }

            $entries[$pageIndex] = $this->parsePageLabelDictionary($labelDictionary);
        }

        return $entries;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pageLabelValueDictionary(string $arrayBody, int &$index, array $objects): ?string
    {
        if (substr($arrayBody, $index, 2) === '<<') {
            return $this->readPdfDictionaryTokenAt($arrayBody, $index);
        }

        if (preg_match('/(\d+)\s+\d+\s+R\b/A', substr($arrayBody, $index), $match) !== 1) {
            return null;
        }

        $index += strlen($match[0]);
        $objectNumber = (int) $match[1];
        return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
    }

    /**
     * @return array{prefix: string, style: string|null, start: int}
     */
    private function parsePageLabelDictionary(string $dictionary): array
    {
        $style = null;
        if (preg_match('/\/S\s*\/([^\s\[\]()<>{}\/%]+)/s', $dictionary, $match) === 1) {
            $candidate = $this->decodePdfName($match[1]);
            $style = in_array($candidate, ['D', 'R', 'r', 'A', 'a'], true) ? $candidate : null;
        }

        $start = 1;
        if (preg_match('/\/St\s+([+-]?\d+)/', $dictionary, $match) === 1) {
            $start = max(1, (int) $match[1]);
        }

        return [
            'prefix' => $this->pageLabelPrefix($dictionary),
            'style' => $style,
            'start' => $start,
        ];
    }

    private function pageLabelPrefix(string $dictionary): string
    {
        if (preg_match('/\/P\s*\(/s', $dictionary, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = strpos($dictionary, '(', $match[0][1]);
            if ($offset === false) {
                return '';
            }

            $token = $this->readLiteralToken($dictionary, $offset);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($token, 1, -1)));
        }

        if (preg_match('/\/P\s*</s', $dictionary, $match, PREG_OFFSET_CAPTURE) === 1) {
            $offset = strpos($dictionary, '<', $match[0][1]);
            if ($offset === false || substr($dictionary, $offset, 2) === '<<') {
                return '';
            }

            $token = $this->readHexToken($dictionary, $offset);
            $hex = preg_replace('/\s+/', '', trim($token, '<>'));
            return $hex === null ? '' : $this->decodeHexString($hex);
        }

        return '';
    }

    private function formatPageLabelNumber(int $number, string $style): string
    {
        return match ($style) {
            'R' => $this->romanPageLabel($number),
            'r' => strtolower($this->romanPageLabel($number)),
            'A' => $this->alphabeticPageLabel($number, false),
            'a' => $this->alphabeticPageLabel($number, true),
            default => (string) $number,
        };
    }

    private function romanPageLabel(int $number): string
    {
        if ($number <= 0) {
            return (string) $number;
        }

        $roman = '';
        foreach ([1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'] as $value => $glyph) {
            while ($number >= $value) {
                $roman .= $glyph;
                $number -= $value;
            }
        }

        return $roman;
    }

    private function alphabeticPageLabel(int $number, bool $lowercase): string
    {
        if ($number <= 0) {
            return (string) $number;
        }

        $letter = chr(ord($lowercase ? 'a' : 'A') + (($number - 1) % 26));
        return str_repeat($letter, intdiv($number - 1, 26) + 1);
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if ($this->isPageObject($body)) {
            return [$objectNumber];
        }

        $kids = $this->pageTreeKidObjectNumbers($body, $objects);
        if ($kids === []) {
            return [];
        }

        $pages = [];
        foreach ($kids as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageTreeKidObjectNumbers(string $body, array $objects): array
    {
        if (preg_match('/\/Kids\s*(?:(\d+)\s+\d+\s+R|\[)/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [];
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            $arrayBody = isset($objects[$objectNumber]) ? $this->pdfArrayAtStart(trim($objects[$objectNumber])) : null;
            return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
        }

        $offset = strpos($body, '[', $match[0][1]);
        $arrayBody = $offset === false ? null : $this->readPdfArrayAt($body, $offset);

        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    private function isCatalogObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Catalog\b/', $body) === 1;
    }

    private function isPageObject(string $body): bool
    {
        return preg_match('/\/Type\s*\/Page\b/', $body) === 1;
    }

    /**
     * @return list<int>
     */
    private function pageContentObjectNumbers(string $pageBody): array
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageBody, $match)) {
            return $this->objectReferences($match[1]);
        }

        if (preg_match('/\/Contents\s+(\d+)\s+\d+\s+R\b/s', $pageBody, $match)) {
            return [(int) $match[1]];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        $entry = $this->streamDictionaryAndPayload($objectBody, $objects);
        if ($entry === null) {
            return null;
        }

        if ($this->isImageStreamDictionary($entry['dict'], $objects)) {
            return null;
        }

        return $this->decodeStream($entry['dict'], $entry['stream'], $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array{dict: string, stream: string}|null
     */
    private function streamDictionaryAndPayload(string $value, array $objects): ?array
    {
        if (preg_match('/<<(.*?)>>\s*stream(?:\r\n|\r|\n)?/s', $value, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $dict = $match[1][0];
        $streamStart = $match[0][1] + strlen($match[0][0]);
        $stream = $this->streamPayloadAt($value, $streamStart, $dict, $objects);
        if ($stream === null) {
            return null;
        }

        return [
            'dict' => $dict,
            'stream' => $stream,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamPayloadAt(string $value, int $streamStart, string $dict, array $objects): ?string
    {
        $length = $this->streamLength($dict, $objects);
        if ($length !== null) {
            if ($length < 0 || $streamStart + $length > strlen($value)) {
                return null;
            }

            return substr($value, $streamStart, $length);
        }

        $end = strpos($value, 'endstream', $streamStart);
        if ($end === false) {
            return null;
        }

        return $this->stripStreamTerminatingLineEnding(substr($value, $streamStart, $end - $streamStart));
    }

    /**
     * @param array<int, string> $objects
     */
    private function streamLength(string $dict, array $objects): ?int
    {
        $offset = $this->nameValueOffset($dict, 'Length');
        if ($offset === null) {
            return null;
        }

        return $this->streamLengthValueAt($dict, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function streamLengthValueAt(string $value, int $offset, array $objects, array $seen = []): ?int
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            if ($objectNumber <= 0 || isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return null;
            }

            $seen[$objectNumber] = true;
            return $this->streamLengthValueAt(trim($objects[$objectNumber]), 0, $objects, $seen);
        }

        if (preg_match('/\G([+-]?\d+)/s', $value, $match, 0, $offset) === 1) {
            $length = (int) $match[1];
            return $length < 0 ? null : $length;
        }

        return null;
    }

    private function stripStreamTerminatingLineEnding(string $stream): string
    {
        if (str_ends_with($stream, "\r\n")) {
            return substr($stream, 0, -2);
        }

        if (str_ends_with($stream, "\n") || str_ends_with($stream, "\r")) {
            return substr($stream, 0, -1);
        }

        return $stream;
    }

    private function isEmbeddedFileStreamDictionary(string $dict): bool
    {
        return preg_match('/\/Type\s*\/EmbeddedFile\b/', $dict) === 1;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects = []): ?string
    {
        $filters = $this->streamFilters($dict, $objects);
        $decodeParms = $this->streamDecodeParms($dict, $objects);
        foreach ($filters as $index => $filter) {
            $filterDecodeParms = $decodeParms[$index] ?? null;
            if (!$this->canApplyDecodeParms($filter, $filterDecodeParms)) {
                return null;
            }

            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $filterDecodeParms),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream, $filterDecodeParms),
                'DCTDecode', 'DCT' => null,
                'CCITTFaxDecode', 'CCF' => null,
                'JPXDecode', 'JBIG2Decode' => null,
                default => $stream,
            };

            if ($decoded === null) {
                return null;
            }
            $stream = $decoded;
        }

        return $stream;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function streamFilters(string $dict, array $objects = []): array
    {
        if (!preg_match('/\/Filter\s*(?:\[(.*?)\]|\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            return $this->filterNamesFromValue($match[1], $objects);
        }

        if (($match[2] ?? '') !== '') {
            return [$this->decodePdfName($match[2])];
        }

        $objectNumber = isset($match[3]) ? (int) $match[3] : 0;
        return $objectNumber > 0 && isset($objects[$objectNumber])
            ? $this->filterNamesFromValue($objects[$objectNumber], $objects)
            : [];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function filterNamesFromValue(string $value, array $objects): array
    {
        preg_match_all('/\/([^\s\[\]()<>{}\/%]+)|(\d+)\s+\d+\s+R\b/', $value, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $objectNumber = isset($match[2]) ? (int) $match[2] : 0;
            if ($objectNumber > 0 && isset($objects[$objectNumber])) {
                foreach ($this->filterNamesFromValue($objects[$objectNumber], $objects) as $filter) {
                    $filters[] = $filter;
                }
            }
        }

        return $filters;
    }

    /**
     * @return list<string|null>
     * @param array<int, string> $objects
     */
    private function streamDecodeParms(string $dict, array $objects): array
    {
        $offset = $this->nameValueOffset($dict, 'DecodeParms');
        if ($offset === null) {
            return [];
        }

        return $this->decodeParmsValueList($dict, $offset, $objects);
    }

    /**
     * @return list<string|null>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsValueList(string $value, int $offset, array $objects, array $seen = []): array
    {
        $offset = $this->skipPdfWhitespace($value, $offset);
        if ($offset >= strlen($value)) {
            return [];
        }

        if ($value[$offset] === '[') {
            $arrayBody = $this->readPdfArrayAt($value, $offset);
            return $arrayBody === null ? [] : $this->decodeParmsArrayItems($arrayBody, $objects, $seen);
        }

        if (substr($value, $offset, 2) === '<<') {
            $dictionary = $this->readPdfDictionaryTokenAt($value, $offset);
            return $dictionary === null ? [] : [$dictionary];
        }

        if (preg_match('/\Gnull\b/s', $value, $match, 0, $offset) === 1) {
            return [null];
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $value, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            if ($objectNumber <= 0 || isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            $seen[$objectNumber] = true;
            return $this->decodeParmsValueList(trim($objects[$objectNumber]), 0, $objects, $seen);
        }

        return [$this->decodeParmsItem(trim(substr($value, $offset)), $objects)];
    }

    /**
     * @return list<string|null>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function decodeParmsArrayItems(string $arrayBody, array $objects, array $seen): array
    {
        $items = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $offset = $this->skipPdfWhitespace($arrayBody, $offset);
            if ($offset >= $length) {
                break;
            }

            if (preg_match('/\Gnull\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $items[] = null;
                $offset += strlen($match[0]);
                continue;
            }

            if (substr($arrayBody, $offset, 2) === '<<') {
                $dictionary = $this->readPdfDictionaryTokenAt($arrayBody, $offset);
                if ($dictionary !== null) {
                    $items[] = $dictionary;
                    continue;
                }
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $arrayBody, $match, 0, $offset) === 1) {
                $items[] = $this->decodeParmsItem($match[0], $objects);
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $items;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeParmsItem(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === 'null') {
            return null;
        }
        if (preg_match('/^(\d+)\s+\d+\s+R$/', $value, $match)) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber]) ? $this->decodeParmsItem($objects[$objectNumber], $objects) : null;
        }
        if (preg_match('/^<<(.*?)>>$/s', $value, $match)) {
            return $match[1];
        }

        return $value;
    }

    private function canApplyDecodeParms(string $filter, ?string $decodeParms): bool
    {
        if ($decodeParms === null || trim($decodeParms) === '') {
            return true;
        }

        if (
            preg_match('/\/Predictor\s+(\d+)/', $decodeParms, $match) === 1
            && (int) $match[1] !== 1
            && !in_array($filter, ['FlateDecode', 'Fl', 'LZWDecode', 'LZW'], true)
        ) {
            return false;
        }

        if (
            in_array($filter, ['LZWDecode', 'LZW'], true)
            && preg_match('/\/EarlyChange\s+(-?\d+)/', $decodeParms, $match) === 1
            && !in_array((int) $match[1], [0, 1], true)
        ) {
            return false;
        }

        return true;
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = trim($stream);
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $terminator = strpos($body, '~>');
        if ($terminator !== false) {
            $body = substr($body, 0, $terminator);
        }

        $out = '';
        $group = [];
        $length = strlen($body);
        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];
            if (ctype_space($char)) {
                continue;
            }

            if ($char === 'z') {
                if ($group !== []) {
                    return null;
                }
                $out .= "\0\0\0\0";
                continue;
            }

            $ord = ord($char);
            if ($ord < 33 || $ord > 117) {
                return null;
            }

            $group[] = $ord - 33;
            if (count($group) === 5) {
                $out .= $this->decodeAscii85Group($group, 4);
                $group = [];
            }
        }

        if ($group !== []) {
            $groupLength = count($group);
            if ($groupLength === 1) {
                return null;
            }
            while (count($group) < 5) {
                $group[] = 84;
            }
            $out .= $this->decodeAscii85Group($group, $groupLength - 1);
        }

        return $out;
    }

    /**
     * @param list<int> $group
     */
    private function decodeAscii85Group(array $group, int $bytesToReturn): string
    {
        $value = 0;
        foreach ($group as $digit) {
            $value = ($value * 85) + $digit;
        }

        $bytes = '';
        for ($shift = 24; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return substr($bytes, 0, $bytesToReturn);
    }

    private function decodeFlateStream(string $stream, ?string $decodeParms = null): ?string
    {
        $inflated = @gzuncompress($stream);
        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream);
        }

        if ($inflated === false) {
            return null;
        }

        return $this->applyDecodeParmsPredictor($inflated, $decodeParms);
    }

    private function decodeLzwStream(string $stream, ?string $decodeParms = null): ?string
    {
        $earlyChange = ($this->decodeParmsInt($decodeParms, 'EarlyChange') ?? 1) === 0 ? 0 : 1;
        $bitOffset = 0;
        $dictionary = [];
        $nextCode = 258;
        $codeSize = 9;

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };
        $resetDictionary();

        $out = '';
        $previous = null;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return $this->applyDecodeParmsPredictor($out, $decodeParms);
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($code === $nextCode && $previous !== null) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            $out .= $entry;
            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }
            $previous = $entry;
        }

        return null;
    }

    private function readLzwCode(string $bytes, int &$bitOffset, int $codeSize): ?int
    {
        $totalBits = strlen($bytes) * 8;
        if ($bitOffset + $codeSize > $totalBits) {
            return null;
        }

        $code = 0;
        for ($index = 0; $index < $codeSize; $index++) {
            $absoluteBit = $bitOffset + $index;
            $byte = ord($bytes[intdiv($absoluteBit, 8)]);
            $shift = 7 - ($absoluteBit % 8);
            $code = ($code << 1) | (($byte >> $shift) & 1);
        }
        $bitOffset += $codeSize;

        return $code;
    }

    private function applyDecodeParmsPredictor(string $bytes, ?string $decodeParms): ?string
    {
        $predictor = $this->decodeParmsInt($decodeParms, 'Predictor') ?? 1;
        if ($predictor === 1) {
            return $bytes;
        }

        $colors = max(1, $this->decodeParmsInt($decodeParms, 'Colors') ?? 1);
        $bitsPerComponent = max(1, $this->decodeParmsInt($decodeParms, 'BitsPerComponent') ?? 8);
        $columns = max(1, $this->decodeParmsInt($decodeParms, 'Columns') ?? 1);
        $rowLength = intdiv(($colors * $columns * $bitsPerComponent) + 7, 8);
        $bytesPerPixel = max(1, intdiv(($colors * $bitsPerComponent) + 7, 8));

        if ($predictor === 2) {
            return $this->applyTiffPredictor($bytes, $rowLength, $bytesPerPixel);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->applyPngPredictor($bytes, $rowLength, $bytesPerPixel);
    }

    private function decodeParmsInt(?string $decodeParms, string $name): ?int
    {
        if ($decodeParms === null || preg_match('/\/' . preg_quote($name, '/') . '\s+(-?\d+)/', $decodeParms, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function applyTiffPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        if ($rowLength < 1 || strlen($bytes) % $rowLength !== 0) {
            return null;
        }

        $out = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $rowLength) {
            $row = substr($bytes, $offset, $rowLength);
            for ($index = $bytesPerPixel; $index < $rowLength; $index++) {
                $row[$index] = chr((ord($row[$index]) + ord($row[$index - $bytesPerPixel])) & 0xff);
            }
            $out .= $row;
        }

        return $out;
    }

    private function applyPngPredictor(string $bytes, int $rowLength, int $bytesPerPixel): ?string
    {
        $stride = $rowLength + 1;
        if ($rowLength < 1 || strlen($bytes) % $stride !== 0) {
            return null;
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $stride) {
            $filter = ord($bytes[$offset]);
            $row = substr($bytes, $offset + 1, $rowLength);
            if ($filter > 4) {
                return null;
            }

            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index >= $bytesPerPixel ? ord($row[$index - $bytesPerPixel]) : 0;
                $up = ord($previous[$index]);
                $upperLeft = $index >= $bytesPerPixel ? ord($previous[$index - $bytesPerPixel]) : 0;
                $encoded = ord($row[$index]);
                $row[$index] = chr(($encoded + $this->pngPredictorValue($filter, $left, $up, $upperLeft)) & 0xff);
            }

            $out .= $row;
            $previous = $row;
        }

        return $out;
    }

    private function pngPredictorValue(int $filter, int $left, int $up, int $upperLeft): int
    {
        return match ($filter) {
            0 => 0,
            1 => $left,
            2 => $up,
            3 => intdiv($left + $up, 2),
            4 => $this->paethPredictor($left, $up, $upperLeft),
        };
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $out = '';
        $length = strlen($stream);
        for ($offset = 0; $offset < $length; $offset++) {
            $control = ord($stream[$offset]);
            if ($control === 128) {
                return $out;
            }

            if ($control <= 127) {
                $copyLength = $control + 1;
                if ($offset + $copyLength >= $length) {
                    return null;
                }
                $out .= substr($stream, $offset + 1, $copyLength);
                $offset += $copyLength;
                continue;
            }

            if ($offset + 1 >= $length) {
                return null;
            }
            $out .= str_repeat($stream[$offset + 1], 257 - $control);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMaps(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $fontObjectMaps = $this->fontObjectMaps($objects);
        if ($fontObjectMaps === []) {
            return [];
        }

        $resourceMaps = [];
        foreach ($objects as $body) {
            $resourceDictionary = $this->resourceDictionaryBody($body, $objects);
            if ($resourceDictionary === null) {
                continue;
            }

            foreach ($this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps) as $name => $map) {
                $resourceMaps[$name] = $map;
            }
        }

        if ($resourceMaps !== []) {
            return $resourceMaps;
        }

        if (count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return [];
    }

    /**
     * @return array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     */
    private function fontObjectMaps(array $objects): array
    {
        $namedCMapBodies = $this->namedCMapBodies($objects);
        $fontObjectMaps = [];

        foreach ($objects as $objectNumber => $body) {
            if (!str_contains($body, '/Type /Font') && !str_contains($body, '/Type/Font')) {
                continue;
            }

            $encodingFallback = $this->fontEncodingMap($body);
            $widthMetrics = $this->fontWidthMetrics($body, $objects);
            $cmap = null;
            if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R\b/', $body, $match)) {
                $cmapObjectNumber = (int) $match[1];
                if (isset($objects[$cmapObjectNumber])) {
                    $cmap = $this->toUnicodeMapFromObject($objects[$cmapObjectNumber], $objects, $namedCMapBodies);
                }
            }

            if (($cmap === null || ($cmap['map'] === [] && $cmap['codeSpaceRanges'] === [])) && $encodingFallback !== null) {
                $cmap = $encodingFallback;
            }

            if ($cmap !== null && ($cmap['map'] !== [] || $cmap['codeSpaceRanges'] !== [])) {
                $cmap = $this->withFontWidthMetrics($cmap, $widthMetrics, $this->fontWritingMode($body, $cmap));
                $fontObjectMaps[$objectNumber] = $cmap;
            }
        }

        return $fontObjectMaps;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function pageFontToUnicodeMaps(int $pageObjectNumber, array $objects, array $fontObjectMaps): array
    {
        $maps = [];
        foreach (array_reverse($this->pageObjectLineage($pageObjectNumber, $objects)) as $objectNumber) {
            $resourceDictionary = $this->resourceDictionaryBody($objects[$objectNumber], $objects);
            if ($resourceDictionary === null) {
                continue;
            }

            foreach ($this->fontResourceMapsFromResourceDictionary($resourceDictionary, $objects, $fontObjectMaps) as $name => $map) {
                $maps[$name] = $map;
            }
        }

        if ($maps === [] && count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return $maps;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function pageObjectLineage(int $pageObjectNumber, array $objects): array
    {
        $lineage = [];
        $seen = [];
        $objectNumber = $pageObjectNumber;

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            $lineage[] = $objectNumber;
            if (!preg_match('/\/Parent\s+(\d+)\s+\d+\s+R\b/s', $objects[$objectNumber], $match)) {
                break;
            }

            $objectNumber = (int) $match[1];
        }

        return $lineage;
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceDictionaryBody(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/\/Resources\s*(?:(\d+)\s+\d+\s+R|<<)/s', $objectBody, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        $offset = strpos($objectBody, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     * @param array<int, string> $objects
     * @param array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontObjectMaps
     */
    private function fontResourceMapsFromResourceDictionary(string $resourceDictionary, array $objects, array $fontObjectMaps): array
    {
        $fontDictionary = $this->fontResourceDictionaryBody($resourceDictionary, $objects);
        if ($fontDictionary === null) {
            return [];
        }

        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $fontDictionary, $resourceMatches, PREG_SET_ORDER)) {
            return [];
        }

        $maps = [];
        foreach ($resourceMatches as $resourceMatch) {
            $fontObjectNumber = (int) $resourceMatch[2];
            if (isset($fontObjectMaps[$fontObjectNumber])) {
                $maps[$this->decodePdfName($resourceMatch[1])] = $fontObjectMaps[$fontObjectNumber];
            }
        }

        return $maps;
    }

    /**
     * @return array<string, array{actualText: string|null, altText: string|null}>
     * @param array<int, string> $objects
     */
    private function markedContentPropertiesFromResourceDictionary(string $resourceDictionary, array $objects): array
    {
        $propertiesDictionary = $this->propertiesResourceDictionaryBody($resourceDictionary, $objects);
        if ($propertiesDictionary === null) {
            return [];
        }

        $properties = [];
        if (preg_match_all(
            '/\/([^\s\[\]()<>{}\/%]+)\s*(?:(\d+)\s+\d+\s+R|<<)/s',
            $propertiesDictionary,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        ) !== false) {
            foreach ($matches as $match) {
                $name = $this->decodePdfName($match[1][0]);
                if (($match[2][0] ?? '') !== '') {
                    $objectNumber = (int) $match[2][0];
                    $dictionary = isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
                } else {
                    $offset = strpos($propertiesDictionary, '<<', $match[0][1]);
                    $dictionary = $offset === false ? null : $this->readPdfDictionaryAt($propertiesDictionary, $offset);
                }

                if ($dictionary === null) {
                    continue;
                }

                $properties[$name] = [
                    'actualText' => $this->pdfOptionalStringValueAfterName($dictionary, 'ActualText', $objects),
                    'altText' => $this->pdfOptionalStringValueAfterName($dictionary, 'Alt', $objects),
                ];
            }
        }

        return $properties;
    }

    /**
     * @param array<int, string> $objects
     */
    private function propertiesResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        if (!preg_match('/\/Properties\s*(?:(\d+)\s+\d+\s+R|<<)/s', $resourceDictionary, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        $offset = strpos($resourceDictionary, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($resourceDictionary, $offset);
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function fontEncodingMap(string $fontBody): ?array
    {
        if (preg_match('/\/Differences\s*\[(.*?)\]/s', $fontBody, $match)) {
            return $this->encodingDifferencesMap($match[1]);
        }

        if (preg_match('/\/Encoding\s+\/([^\s\[\]()<>{}\/%]+)/', $fontBody, $match)) {
            return $this->namedEncodingMap($this->decodePdfName($match[1]));
        }

        return null;
    }

    /**
     * @return array{widths: array<int, float>, defaultWidth: float|null, cidSet: array<int, true>|null, verticalDisplacements: array<int, float>, defaultVerticalDisplacement: float|null}
     * @param array<int, string> $objects
     */
    private function fontWidthMetrics(string $fontBody, array $objects): array
    {
        $widths = [];
        $defaultWidth = null;
        $cidSet = null;
        $hasWidthArray = false;
        $verticalDisplacements = [];
        $defaultVerticalDisplacement = null;
        $hasVerticalWidthArray = false;

        foreach ($this->type3CharProcWidths($fontBody, $objects) as $code => $width) {
            $widths[$code] = $width;
        }

        foreach ([$fontBody, ...$this->descendantFontBodies($fontBody, $objects)] as $body) {
            $widthArray = $this->pdfArrayValueAfterName($body, 'W');
            if ($widthArray !== null) {
                $hasWidthArray = true;
                foreach ($this->cidWidthsFromWArray($widthArray) as $cid => $width) {
                    $widths[$cid] = $width;
                }
            }

            $bodyDefaultWidth = $this->pdfNumberValueAfterName($body, 'DW');
            if ($bodyDefaultWidth !== null) {
                $defaultWidth = $bodyDefaultWidth;
            }

            $verticalWidthArray = $this->pdfArrayValueAfterName($body, 'W2');
            if ($verticalWidthArray !== null) {
                $hasVerticalWidthArray = true;
                foreach ($this->cidVerticalDisplacementsFromW2Array($verticalWidthArray) as $cid => $displacement) {
                    $verticalDisplacements[$cid] = $displacement;
                }
            }

            $verticalDefaultMetrics = $this->pdfArrayValueAfterName($body, 'DW2');
            if ($verticalDefaultMetrics !== null) {
                $metrics = $this->numbersFromPdfArray($verticalDefaultMetrics);
                if (count($metrics) >= 2) {
                    $defaultVerticalDisplacement = (float) $metrics[1];
                }
            }

            $bodyCidSet = $this->cidSetFromCidFontBody($body, $objects);
            if ($bodyCidSet !== null) {
                $cidSet = $cidSet === null ? $bodyCidSet : ($cidSet + $bodyCidSet);
            }
        }

        if (($hasWidthArray || $cidSet !== null) && $defaultWidth === null) {
            $defaultWidth = 1000.0;
        }
        if ($hasVerticalWidthArray && $defaultVerticalDisplacement === null) {
            $defaultVerticalDisplacement = -1000.0;
        }

        return [
            'widths' => $widths,
            'defaultWidth' => $defaultWidth,
            'cidSet' => $cidSet,
            'verticalDisplacements' => $verticalDisplacements,
            'defaultVerticalDisplacement' => $defaultVerticalDisplacement,
        ];
    }

    /**
     * @return array<int, float>
     * @param array<int, string> $objects
     */
    private function type3CharProcWidths(string $fontBody, array $objects): array
    {
        if (preg_match('/\/Subtype\s*\/Type3\b/', $fontBody) !== 1) {
            return [];
        }

        $glyphNamesByCode = $this->encodingDifferencesGlyphNames($fontBody);
        $charProcObjectNumbers = $this->charProcObjectNumbers($fontBody, $objects);
        if ($glyphNamesByCode === [] || $charProcObjectNumbers === []) {
            return [];
        }

        $widths = [];
        foreach ($glyphNamesByCode as $code => $glyphName) {
            $objectNumber = $charProcObjectNumbers[$glyphName] ?? null;
            if ($objectNumber === null || !isset($objects[$objectNumber])) {
                continue;
            }

            $width = $this->type3CharProcDeclaredWidth($objects[$objectNumber], $objects);
            if ($width !== null) {
                $widths[$code] = $width;
            }
        }

        return $widths;
    }

    /**
     * @return array<int, string>
     */
    private function encodingDifferencesGlyphNames(string $fontBody): array
    {
        if (preg_match('/\/Differences\s*\[(.*?)\]/s', $fontBody, $match) !== 1) {
            return [];
        }

        preg_match_all('/\/[^\s\[\]()<>{}\/%]+|[+-]?\d+/', $match[1], $tokens);
        $glyphNames = [];
        $code = null;
        foreach ($tokens[0] ?? [] as $token) {
            if (preg_match('/^[+-]?\d+$/', $token) === 1) {
                $code = max(0, min(255, (int) $token));
                continue;
            }

            if ($code === null || !str_starts_with($token, '/')) {
                continue;
            }

            $glyphNames[$code] = $this->decodePdfName(substr($token, 1));
            $code++;
        }

        return $glyphNames;
    }

    /**
     * @return array<string, int>
     * @param array<int, string> $objects
     */
    private function charProcObjectNumbers(string $fontBody, array $objects): array
    {
        $dictionary = $this->charProcsDictionaryBody($fontBody, $objects);
        if ($dictionary === null) {
            return [];
        }

        if (!preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+(\d+)\s+\d+\s+R\b/', $dictionary, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $objectNumbers = [];
        foreach ($matches as $match) {
            $objectNumbers[$this->decodePdfName($match[1])] = (int) $match[2];
        }

        return $objectNumbers;
    }

    /**
     * @param array<int, string> $objects
     */
    private function charProcsDictionaryBody(string $fontBody, array $objects): ?string
    {
        if (!preg_match('/\/CharProcs\s*(?:(\d+)\s+\d+\s+R|<<)/s', $fontBody, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        $offset = strpos($fontBody, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($fontBody, $offset);
    }

    /**
     * @param array<int, string> $objects
     */
    private function type3CharProcDeclaredWidth(string $objectBody, array $objects): ?float
    {
        $charProc = $this->decodeStreamObject($objectBody, $objects) ?? trim($objectBody);
        $operands = [];

        foreach ($this->contentTokens($charProc) as $token) {
            if ($token === 'd0') {
                if (count($operands) < 2) {
                    return null;
                }

                return $this->numericOperand($operands[count($operands) - 2]);
            }

            if ($token === 'd1') {
                if (count($operands) < 6) {
                    return null;
                }

                return $this->numericOperand($operands[count($operands) - 6]);
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return null;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function descendantFontBodies(string $fontBody, array $objects): array
    {
        $descendantFonts = $this->pdfArrayValueAfterName($fontBody, 'DescendantFonts');
        if ($descendantFonts === null) {
            return [];
        }

        $bodies = [];
        foreach ($this->objectReferences($descendantFonts) as $objectNumber) {
            if (isset($objects[$objectNumber])) {
                $bodies[] = $objects[$objectNumber];
            }
        }

        $offset = 0;
        while (($offset = strpos($descendantFonts, '<<', $offset)) !== false) {
            $dictionary = $this->readPdfDictionaryAt($descendantFonts, $offset);
            $dictionaryEnd = $this->pdfDictionaryEndOffset($descendantFonts, $offset);
            if ($dictionary === null || $dictionaryEnd === null) {
                break;
            }
            $bodies[] = $dictionary;
            $offset = $dictionaryEnd + 1;
        }

        return $bodies;
    }

    /**
     * @return array<int, float>
     */
    private function cidWidthsFromWArray(string $arrayBody): array
    {
        $tokens = $this->contentTokens($arrayBody);
        $widths = [];

        for ($index = 0, $count = count($tokens); $index < $count;) {
            $firstCid = $this->integerToken($tokens[$index] ?? '');
            if ($firstCid === null) {
                $index++;
                continue;
            }
            $index++;

            $next = $tokens[$index] ?? null;
            if ($next === null) {
                break;
            }

            if (str_starts_with(trim($next), '[')) {
                foreach ($this->integersFromPdfArray(substr(trim($next), 1, -1)) as $offset => $width) {
                    $cid = $firstCid + $offset;
                    if ($cid >= 0 && $cid <= 0xffff) {
                        $widths[$cid] = (float) $width;
                    }
                }
                $index++;
                continue;
            }

            $lastCid = $this->integerToken($next);
            $width = $this->integerToken($tokens[$index + 1] ?? '');
            if ($lastCid === null || $width === null) {
                continue;
            }

            $index += 2;
            if ($firstCid < 0 || $lastCid < $firstCid) {
                continue;
            }

            for ($cid = $firstCid, $limit = min($lastCid, 0xffff); $cid <= $limit; $cid++) {
                $widths[$cid] = (float) $width;
            }
        }

        return $widths;
    }

    /**
     * @return array<int, float>
     */
    private function cidVerticalDisplacementsFromW2Array(string $arrayBody): array
    {
        $tokens = $this->contentTokens($arrayBody);
        $displacements = [];

        for ($index = 0, $count = count($tokens); $index < $count;) {
            $firstCid = $this->integerToken($tokens[$index] ?? '');
            if ($firstCid === null) {
                $index++;
                continue;
            }
            $index++;

            $next = $tokens[$index] ?? null;
            if ($next === null) {
                break;
            }

            if (str_starts_with(trim($next), '[')) {
                $metrics = $this->numbersFromPdfArray(substr(trim($next), 1, -1));
                for ($offset = 0, $metricCount = count($metrics); $offset + 2 < $metricCount; $offset += 3) {
                    $cid = $firstCid + intdiv($offset, 3);
                    if ($cid >= 0 && $cid <= 0xffff) {
                        $displacements[$cid] = (float) $metrics[$offset];
                    }
                }
                $index++;
                continue;
            }

            $lastCid = $this->integerToken($next);
            $verticalDisplacement = $this->numericOperand($tokens[$index + 1] ?? '');
            $positionX = $this->numericOperand($tokens[$index + 2] ?? '');
            $positionY = $this->numericOperand($tokens[$index + 3] ?? '');
            if ($lastCid === null || $verticalDisplacement === null || $positionX === null || $positionY === null) {
                $index++;
                continue;
            }

            $index += 4;
            if ($firstCid < 0 || $lastCid < $firstCid) {
                continue;
            }

            for ($cid = $firstCid, $limit = min($lastCid, 0xffff); $cid <= $limit; $cid++) {
                $displacements[$cid] = (float) $verticalDisplacement;
            }
        }

        return $displacements;
    }

    /**
     * @return array<int, true>|null
     * @param array<int, string> $objects
     */
    private function cidSetFromCidFontBody(string $fontBody, array $objects): ?array
    {
        if (preg_match('/\/Subtype\s*\/CIDFontType[02]\b/s', $fontBody) !== 1) {
            return null;
        }

        $descriptor = $this->fontDescriptorBody($fontBody, $objects);
        if ($descriptor === null) {
            return null;
        }

        $cidSetObjectNumber = $this->objectReferenceValueAfterName($descriptor, 'CIDSet');
        if ($cidSetObjectNumber === null || !isset($objects[$cidSetObjectNumber])) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objects[$cidSetObjectNumber], $objects);
        if ($decoded === null || $decoded === '') {
            return null;
        }

        return $this->cidSetBits($decoded);
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontDescriptorBody(string $fontBody, array $objects): ?string
    {
        $descriptorObjectNumber = $this->objectReferenceValueAfterName($fontBody, 'FontDescriptor');
        if ($descriptorObjectNumber !== null) {
            return $objects[$descriptorObjectNumber] ?? null;
        }

        if (preg_match('/\/FontDescriptor\s*<</s', $fontBody, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = strpos($fontBody, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($fontBody, $offset);
    }

    /**
     * @return array<int, true>|null
     */
    private function cidSetBits(string $bytes): ?array
    {
        $cids = [];
        for ($byteIndex = 0, $length = strlen($bytes); $byteIndex < $length; $byteIndex++) {
            $byte = ord($bytes[$byteIndex]);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($byte & (1 << (7 - $bit))) === 0) {
                    continue;
                }

                $cid = ($byteIndex * 8) + $bit;
                if ($cid > 0xffff) {
                    break 2;
                }

                $cids[$cid] = true;
            }
        }

        return $cids === [] ? null : $cids;
    }

    private function integerToken(string $token): ?int
    {
        if (preg_match('/^[+-]?\d+$/', $token) !== 1) {
            return null;
        }

        return (int) $token;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>} $map
     * @param array{widths: array<int, float>, defaultWidth: float|null, cidSet: array<int, true>|null, verticalDisplacements: array<int, float>, defaultVerticalDisplacement: float|null} $metrics
     * @return array<string, mixed>
     */
    private function withFontWidthMetrics(array $map, array $metrics, int $writingMode): array
    {
        if (
            $writingMode === 0
            && $metrics['widths'] === []
            && $metrics['defaultWidth'] === null
            && $metrics['cidSet'] === null
            && $metrics['verticalDisplacements'] === []
            && $metrics['defaultVerticalDisplacement'] === null
        ) {
            return $map;
        }

        $map['writingMode'] = $writingMode;
        $map['cidWidths'] = $metrics['widths'];
        $map['cidDefaultWidth'] = $metrics['defaultWidth'];
        $map['cidVerticalDisplacements'] = $metrics['verticalDisplacements'];
        $map['cidDefaultVerticalDisplacement'] = $writingMode === 1
            ? ($metrics['defaultVerticalDisplacement'] ?? -1000.0)
            : $metrics['defaultVerticalDisplacement'];
        if ($metrics['cidSet'] !== null) {
            $map['cidSet'] = $metrics['cidSet'];
        }

        return $map;
    }

    private function fontWritingMode(string $fontBody, array $cmap): int
    {
        if (preg_match('/\/Encoding\s+\/([^\s\[\]()<>{}\/%]+)/', $fontBody, $match)) {
            $encodingName = $this->decodePdfName($match[1]);
            if ($encodingName === 'Identity-V') {
                return 1;
            }
            if ($encodingName === 'Identity-H') {
                return 0;
            }
        }

        return $this->mapWritingMode($cmap);
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontResourceDictionaryBody(string $resourceDictionary, array $objects): ?string
    {
        if (!preg_match('/\/Font\s*(?:(\d+)\s+\d+\s+R|<<)/s', $resourceDictionary, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        if (($match[1][0] ?? '') !== '') {
            $objectNumber = (int) $match[1][0];
            return isset($objects[$objectNumber]) ? $this->dictionaryObjectBody($objects[$objectNumber]) : null;
        }

        $offset = strpos($resourceDictionary, '<<', $match[0][1]);
        return $offset === false ? null : $this->readPdfDictionaryAt($resourceDictionary, $offset);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset): ?string
    {
        return $this->readPdfDictionaryTokenAt($value, $offset);
    }

    private function readPdfDictionaryTokenAt(string $value, int &$offset): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        $index = $offset;
        $length = strlen($value);
        while ($index < $length - 1) {
            if ($value[$index] === '(') {
                $this->readLiteralToken($value, $index);
                continue;
            }

            if ($value[$index] === '<' && $value[$index + 1] !== '<') {
                $this->readHexToken($value, $index);
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index += 2;
                continue;
            }

            if ($pair === '>>') {
                $depth--;
                $index += 2;
                if ($depth === 0) {
                    $offset = $index;
                    return substr($value, $bodyStart, $index - 2 - $bodyStart);
                }
                continue;
            }

            $index++;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfStringTokenAt($body, $offset, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfOptionalStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->pdfStringTokenAt($body, $offset, $objects);
    }

    private function pdfArrayValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        return $this->readPdfArrayAt($body, $offset);
    }

    private function pdfNumberValueAfterName(string $body, string $name): ?float
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || preg_match('/\G([+-]?(?:\d+(?:\.\d*)?|\.\d+))/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return (float) $match[1];
    }

    private function pdfIntegerValueAfterName(string $body, string $name): ?int
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || preg_match('/\G([+-]?\d+)/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null) {
            return null;
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || ($body[$offset] ?? '') !== '/') {
            return null;
        }

        $end = $offset + 1;
        while ($end < strlen($body) && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$end])) {
            $end++;
        }

        return $this->decodePdfName(substr($body, $offset + 1, $end - $offset - 1));
    }

    private function pdfValueAfterName(string $body, string $name): ?string
    {
        $offset = $this->nameValueOffset($body, $name);
        if ($offset === null || $offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '[') {
            $array = $this->readPdfArrayAt($body, $offset);
            return $array === null ? null : '[' . $array . ']';
        }

        if (substr($body, $offset, 2) === '<<') {
            $end = $this->pdfDictionaryEndOffset($body, $offset);
            return $end === null ? null : substr($body, $offset, $end - $offset + 1);
        }

        if ($body[$offset] === '(') {
            $end = $this->skipPdfLiteralStringAt($body, $offset);
            return $end === null ? null : substr($body, $offset, $end - $offset + 1);
        }

        if ($body[$offset] === '<') {
            $end = strpos($body, '>', $offset + 1);
            return $end === false ? null : substr($body, $offset, $end - $offset + 1);
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            return $match[0];
        }

        if ($body[$offset] === '/') {
            $end = $offset + 1;
            while ($end < strlen($body) && !str_contains(" \t\r\n\f[]()<>{}/%", $body[$end])) {
                $end++;
            }

            return substr($body, $offset, $end - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end === $offset ? null : substr($body, $offset, $end - $offset);
    }

    /**
     * @return list<string>
     */
    private function pdfArrayItems(string $arrayBody): array
    {
        $items = [];
        $index = 0;
        $length = strlen($arrayBody);

        while ($index < $length) {
            $this->skipContentWhitespaceAndComments($arrayBody, $index);
            if ($index >= $length) {
                break;
            }

            if (preg_match('/\G\d+\s+\d+\s+R\b/s', $arrayBody, $match, 0, $index) === 1) {
                $items[] = $match[0];
                $index += strlen($match[0]);
                continue;
            }

            $char = $arrayBody[$index];
            if (substr($arrayBody, $index, 2) === '<<') {
                $items[] = $this->readDictionaryToken($arrayBody, $index);
                continue;
            }

            if ($char === '[') {
                $items[] = $this->readArrayToken($arrayBody, $index);
                continue;
            }

            if ($char === '(') {
                $items[] = $this->readLiteralToken($arrayBody, $index);
                continue;
            }

            if ($char === '<') {
                $items[] = $this->readHexToken($arrayBody, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($arrayBody[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $items[] = substr($arrayBody, $start, $index - $start);
        }

        return array_values(array_filter($items, static fn (string $item): bool => trim($item) !== ''));
    }

    private function nameValueOffset(string $body, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $this->skipPdfWhitespace($body, $match[0][1] + strlen($match[0][0]));
    }

    private function skipPdfWhitespace(string $value, int $offset): int
    {
        $length = strlen($value);
        while ($offset < $length && ctype_space($value[$offset])) {
            $offset++;
        }

        return $offset;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringTokenAt(string $body, int $offset, array $objects): ?string
    {
        if ($offset >= strlen($body)) {
            return null;
        }

        $char = $body[$offset];
        if ($char === '(') {
            $raw = $this->readPdfLiteralStringAt($body, $offset);
            return $raw === null ? null : $this->decodePdfStringBytes($this->decodeLiteralString($raw));
        }

        if ($char === '<' && substr($body, $offset, 2) !== '<<') {
            $bytes = $this->readPdfHexStringAt($body, $offset);
            return $bytes === null ? null : $this->decodePdfStringBytes($bytes);
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            $objectNumber = (int) $match[1];
            return isset($objects[$objectNumber])
                ? $this->pdfStringTokenAt(trim($objects[$objectNumber]), 0, $objects)
                : null;
        }

        if ($char === '/') {
            $end = strcspn($body, " \t\r\n\f[]()<>{}/%", $offset + 1);
            return $this->decodePdfName(substr($body, $offset + 1, $end));
        }

        return null;
    }

    private function readPdfLiteralStringAt(string $value, int $offset): ?string
    {
        if ($offset >= strlen($value) || $value[$offset] !== '(') {
            return null;
        }

        $depth = 0;
        $raw = '';
        for ($index = $offset + 1, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                if ($index + 1 < $length) {
                    $raw .= $char . $value[$index + 1];
                    $index++;
                    continue;
                }

                $raw .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $raw .= $char;
                continue;
            }

            if ($char === ')') {
                if ($depth === 0) {
                    return $raw;
                }
                $depth--;
                $raw .= $char;
                continue;
            }

            $raw .= $char;
        }

        return null;
    }

    private function readPdfHexStringAt(string $value, int $offset): ?string
    {
        $end = strpos($value, '>', $offset + 1);
        if ($end === false) {
            return null;
        }

        $hex = preg_replace('/\s+/', '', substr($value, $offset + 1, $end - $offset - 1));
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? null : $bytes;
    }

    private function pdfArrayAtStart(string $value): ?string
    {
        return str_starts_with($value, '[') ? $this->readPdfArrayAt($value, 0) : null;
    }

    private function readPdfArrayAt(string $value, int $offset): ?string
    {
        if ($offset >= strlen($value) || $value[$offset] !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                if ($skipped === null) {
                    return null;
                }
                $index = $skipped;
                continue;
            }

            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $dictionaryEnd = $this->pdfDictionaryEndOffset($value, $index);
                if ($dictionaryEnd === null) {
                    return null;
                }
                $index = $dictionaryEnd;
                continue;
            }

            if ($char === '[') {
                $depth++;
                continue;
            }

            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipPdfLiteralStringAt(string $value, int $offset): ?int
    {
        if ($offset >= strlen($value) || $value[$offset] !== '(') {
            return null;
        }

        $depth = 0;
        for ($index = $offset + 1, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                if ($depth === 0) {
                    return $index;
                }
                $depth--;
            }
        }

        return null;
    }

    private function pdfDictionaryEndOffset(string $value, int $offset): ?int
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            if ($value[$index] === '(') {
                $skipped = $this->skipPdfLiteralStringAt($value, $index);
                if ($skipped === null) {
                    return null;
                }
                $index = $skipped;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
            $index++;
        }

        return null;
    }

    private function firstObjectReference(string $value): ?int
    {
        if (preg_match('/(\d+)\s+\d+\s+R\b/s', $value, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     */
    private function encodingDifferencesMap(string $differences): array
    {
        preg_match_all('/\/[^\s\[\]()<>{}\/%]+|[+-]?\d+/', $differences, $matches);
        $map = [];
        $code = null;

        foreach ($matches[0] ?? [] as $token) {
            if (preg_match('/^[+-]?\d+$/', $token) === 1) {
                $code = max(0, min(255, (int) $token));
                continue;
            }

            if ($code === null || !str_starts_with($token, '/')) {
                continue;
            }

            $glyph = $this->glyphNameToUnicode($this->decodePdfName(substr($token, 1)));
            if ($glyph !== '') {
                $map[str_pad(strtolower(dechex($code)), 2, '0', STR_PAD_LEFT)] = $glyph;
            }
            $code++;
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function namedEncodingMap(string $encodingName): ?array
    {
        if ($encodingName === 'Identity-H' || $encodingName === 'Identity-V') {
            return [
                'map' => [],
                'codeSpaceRanges' => [
                    ['start' => 0, 'end' => 0xffff, 'width' => 4],
                ],
                'writingMode' => $encodingName === 'Identity-V' ? 1 : 0,
            ];
        }

        if ($encodingName !== 'WinAnsiEncoding') {
            return null;
        }

        return [
            'map' => [
                '80' => $this->unicodeCodepoint(0x20ac),
                '82' => $this->unicodeCodepoint(0x201a),
                '83' => $this->unicodeCodepoint(0x0192),
                '84' => $this->unicodeCodepoint(0x201e),
                '85' => $this->unicodeCodepoint(0x2026),
                '86' => $this->unicodeCodepoint(0x2020),
                '87' => $this->unicodeCodepoint(0x2021),
                '88' => $this->unicodeCodepoint(0x02c6),
                '89' => $this->unicodeCodepoint(0x2030),
                '8a' => $this->unicodeCodepoint(0x0160),
                '8b' => $this->unicodeCodepoint(0x2039),
                '8c' => $this->unicodeCodepoint(0x0152),
                '8e' => $this->unicodeCodepoint(0x017d),
                '91' => $this->unicodeCodepoint(0x2018),
                '92' => $this->unicodeCodepoint(0x2019),
                '93' => $this->unicodeCodepoint(0x201c),
                '94' => $this->unicodeCodepoint(0x201d),
                '95' => $this->unicodeCodepoint(0x2022),
                '96' => $this->unicodeCodepoint(0x2013),
                '97' => $this->unicodeCodepoint(0x2014),
                '98' => $this->unicodeCodepoint(0x02dc),
                '99' => $this->unicodeCodepoint(0x2122),
                '9a' => $this->unicodeCodepoint(0x0161),
                '9b' => $this->unicodeCodepoint(0x203a),
                '9c' => $this->unicodeCodepoint(0x0153),
                '9e' => $this->unicodeCodepoint(0x017e),
                '9f' => $this->unicodeCodepoint(0x0178),
            ],
            'codeSpaceRanges' => [
                ['start' => 0, 'end' => 255, 'width' => 2],
            ],
        ];
    }

    private function unicodeCodepoint(int $codepoint): string
    {
        $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
        return $decoded === false ? '' : $decoded;
    }

    private function glyphNameToUnicode(string $glyphName): string
    {
        $baseName = explode('.', $glyphName, 2)[0];
        if ($baseName === '') {
            return '';
        }

        if (preg_match('/^uni([\da-fA-F]{4})(?:[\da-fA-F]{4})*$/', $baseName) === 1) {
            $hex = substr($baseName, 3);
            return $this->decodeCMapUnicodeHex($hex);
        }

        if (preg_match('/^u([\da-fA-F]{4,6})$/', $baseName, $match) === 1) {
            $codepoint = hexdec($match[1]);
            if ($codepoint <= 0x10ffff) {
                $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $codepoint));
                return $decoded === false ? '' : $decoded;
            }
        }

        $names = [
            'space' => ' ',
            'hyphen' => '-',
            'minus' => '-',
            'period' => '.',
            'comma' => ',',
            'colon' => ':',
            'semicolon' => ';',
            'parenleft' => '(',
            'parenright' => ')',
            'slash' => '/',
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
            'D' => 'D',
            'E' => 'E',
            'F' => 'F',
            'G' => 'G',
            'H' => 'H',
            'I' => 'I',
            'J' => 'J',
            'K' => 'K',
            'L' => 'L',
            'M' => 'M',
            'N' => 'N',
            'O' => 'O',
            'P' => 'P',
            'Q' => 'Q',
            'R' => 'R',
            'S' => 'S',
            'T' => 'T',
            'U' => 'U',
            'V' => 'V',
            'W' => 'W',
            'X' => 'X',
            'Y' => 'Y',
            'Z' => 'Z',
            'a' => 'a',
            'b' => 'b',
            'c' => 'c',
            'd' => 'd',
            'e' => 'e',
            'f' => 'f',
            'g' => 'g',
            'h' => 'h',
            'i' => 'i',
            'j' => 'j',
            'k' => 'k',
            'l' => 'l',
            'm' => 'm',
            'n' => 'n',
            'o' => 'o',
            'p' => 'p',
            'q' => 'q',
            'r' => 'r',
            's' => 's',
            't' => 't',
            'u' => 'u',
            'v' => 'v',
            'w' => 'w',
            'x' => 'x',
            'y' => 'y',
            'z' => 'z',
        ];

        return $names[$baseName] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        if ($this->hasEncryptedTrailer($pdfBytes)) {
            return [];
        }

        $definitions = $this->directObjectDefinitions($pdfBytes);
        if ($definitions === []) {
            return [];
        }

        $preliminaryObjects = $this->latestDirectObjects($definitions);
        $xrefEntries = $this->xrefEntries($pdfBytes, $preliminaryObjects);
        $objects = $this->liveDirectObjects($definitions, $xrefEntries);

        foreach ($this->objectsFromObjectStreams($objects, $xrefEntries) as $objectNumber => $body) {
            $objects[$objectNumber] = $body;
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    private function hasEncryptedTrailer(string $pdfBytes): bool
    {
        foreach ($this->trailerDictionaryBodies($pdfBytes) as $trailer) {
            $value = $this->pdfValueAfterName($trailer, 'Encrypt');
            if ($value !== null && trim($value) !== 'null') {
                return true;
            }
        }

        if (!preg_match_all('/\d+\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $body = $match[1];
            if (preg_match('/\/Type\s*\/XRef\b/s', $body) !== 1) {
                continue;
            }

            $value = $this->pdfValueAfterName($body, 'Encrypt');
            if ($value !== null && trim($value) !== 'null') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function trailerDictionaryBodies(string $pdfBytes): array
    {
        $trailers = [];
        if (!preg_match_all('/(?:^|[\r\n])trailer\s*<</s', $pdfBytes, $matches, PREG_OFFSET_CAPTURE)) {
            return $trailers;
        }

        foreach ($matches[0] as $match) {
            $offset = strpos($pdfBytes, '<<', $match[1]);
            if ($offset === false) {
                continue;
            }

            $dictionary = $this->readPdfDictionaryAt($pdfBytes, $offset);
            if ($dictionary !== null) {
                $trailers[] = $dictionary;
            }
        }

        return $trailers;
    }

    /**
     * @return array<int, list<array{generation: int, offset: int, body: string}>>
     */
    private function directObjectDefinitions(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $definitions = [];
        foreach ($matches as $match) {
            $objectNumber = (int) $match[1][0];
            $definitions[$objectNumber][] = [
                'generation' => (int) $match[2][0],
                'offset' => $match[0][1],
                'body' => $match[3][0],
            ];
        }
        ksort($definitions, SORT_NUMERIC);

        return $definitions;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @return array<int, string>
     */
    private function latestDirectObjects(array $definitions): array
    {
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->latestDirectObjectDefinition($entries);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
            }
        }

        return $objects;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function latestDirectObjectDefinition(array $definitions): ?array
    {
        if ($definitions === []) {
            return null;
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => [$left['generation'], $left['offset']] <=> [$right['generation'], $right['offset']]
        );

        $selected = end($definitions);
        return is_array($selected) ? $selected : null;
    }

    /**
     * @param array<int, list<array{generation: int, offset: int, body: string}>> $definitions
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}> $xrefEntries
     * @return array<int, string>
     */
    private function liveDirectObjects(array $definitions, array $xrefEntries): array
    {
        $objects = [];
        foreach ($definitions as $objectNumber => $entries) {
            $selected = $this->liveDirectObjectDefinition($entries, $xrefEntries[$objectNumber] ?? null);
            if ($selected !== null) {
                $objects[$objectNumber] = $selected['body'];
            }
        }
        ksort($objects, SORT_NUMERIC);

        return $objects;
    }

    /**
     * @param list<array{generation: int, offset: int, body: string}> $definitions
     * @param array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}|null $xrefEntry
     * @return array{generation: int, offset: int, body: string}|null
     */
    private function liveDirectObjectDefinition(array $definitions, ?array $xrefEntry): ?array
    {
        if ($xrefEntry === null) {
            return $this->latestDirectObjectDefinition($definitions);
        }

        if (($xrefEntry['type'] ?? 1) !== 1) {
            return null;
        }

        $generation = $xrefEntry['generation'] ?? null;
        $offset = $xrefEntry['offset'] ?? null;
        $candidates = [];
        foreach ($definitions as $definition) {
            if ($generation !== null && $definition['generation'] !== $generation) {
                continue;
            }
            if ($offset !== null && $definition['offset'] === $offset) {
                return $definition;
            }
            $candidates[] = $definition;
        }

        return $this->latestDirectObjectDefinition($candidates);
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     */
    private function xrefEntries(string $pdfBytes, array $objects): array
    {
        $entries = $this->xrefTableEntries($pdfBytes);
        foreach ($this->xrefStreamEntries($objects) as $objectNumber => $entry) {
            $entries[$objectNumber] = $entry;
        }

        ksort($entries, SORT_NUMERIC);

        return $entries;
    }

    /**
     * @return array<int, array{type: int, generation: int, offset: int}>
     */
    private function xrefTableEntries(string $pdfBytes): array
    {
        $entries = [];
        if (!preg_match_all('/(?:^|[\r\n])xref\s*(.*?)trailer\s*<</s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $entries;
        }

        foreach ($matches as $match) {
            $lines = preg_split('/\r\n|\r|\n/', trim($match[1]));
            if ($lines === false) {
                continue;
            }

            for ($lineIndex = 0, $lineCount = count($lines); $lineIndex < $lineCount; $lineIndex++) {
                $line = trim($lines[$lineIndex]);
                if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header) !== 1) {
                    continue;
                }

                $startObject = (int) $header[1];
                $count = max(0, (int) $header[2]);
                for ($entryIndex = 0; $entryIndex < $count && $lineIndex + 1 < $lineCount; $entryIndex++) {
                    $row = trim($lines[++$lineIndex]);
                    if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $row, $rowMatch) !== 1) {
                        continue;
                    }

                    $entries[$startObject + $entryIndex] = [
                        'type' => $rowMatch[3] === 'n' ? 1 : 0,
                        'generation' => (int) $rowMatch[2],
                        'offset' => (int) $rowMatch[1],
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     * @param array<int, string> $objects
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}> $xrefEntries
     */
    private function objectsFromObjectStreams(array $objects, array $xrefEntries): array
    {
        $expanded = [];

        foreach ($objects as $objectStreamNumber => $body) {
            if (preg_match('/\/Type\s*\/ObjStm\b/', $body) !== 1) {
                continue;
            }

            $decoded = $this->decodeStreamObject($body, $objects);
            if ($decoded === null) {
                continue;
            }

            if (
                preg_match('/\/N\s+(\d+)/', $body, $countMatch) !== 1
                || preg_match('/\/First\s+(\d+)/', $body, $firstMatch) !== 1
            ) {
                continue;
            }

            $count = max(0, (int) $countMatch[1]);
            $first = (int) $firstMatch[1];
            if ($count === 0 || $first < 0 || $first >= strlen($decoded)) {
                continue;
            }

            $header = substr($decoded, 0, $first);
            if (!preg_match_all('/(\d+)\s+(\d+)/', $header, $pairs, PREG_SET_ORDER)) {
                continue;
            }

            $pairs = array_slice($pairs, 0, $count);
            $hasCompressedXrefEntriesForStream = $this->hasCompressedXrefEntriesForObjectStream($xrefEntries, $objectStreamNumber);
            foreach ($pairs as $index => $pair) {
                $objectNumber = (int) $pair[1];
                $offset = (int) $pair[2];
                if ($hasCompressedXrefEntriesForStream) {
                    $xrefEntry = $xrefEntries[$objectNumber] ?? null;
                    if (
                        $xrefEntry === null
                        || ($xrefEntry['type'] ?? null) !== 2
                        || $xrefEntry['objectStream'] !== $objectStreamNumber
                        || $xrefEntry['index'] !== $index
                    ) {
                        continue;
                    }
                }

                $nextOffset = isset($pairs[$index + 1]) ? (int) $pairs[$index + 1][2] : strlen($decoded) - $first;
                if ($offset < 0 || $nextOffset < $offset) {
                    continue;
                }

                $memberBody = trim(substr($decoded, $first + $offset, $nextOffset - $offset));
                if ($memberBody !== '') {
                    $expanded[$objectNumber] = $memberBody;
                }
            }
        }

        return $expanded;
    }

    /**
     * @param array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}> $xrefEntries
     */
    private function hasCompressedXrefEntriesForObjectStream(array $xrefEntries, int $objectStreamNumber): bool
    {
        foreach ($xrefEntries as $entry) {
            if (($entry['type'] ?? null) === 2 && ($entry['objectStream'] ?? null) === $objectStreamNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{type: int, generation?: int, offset?: int, objectStream?: int, index?: int}>
     * @param array<int, string> $objects
     */
    private function xrefStreamEntries(array $objects): array
    {
        $entries = [];
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/XRef\b/', $body) !== 1) {
                continue;
            }

            if (preg_match('/\/W\s*\[(.*?)\]/s', $body, $widthMatch) !== 1) {
                continue;
            }

            $widths = $this->integersFromPdfArray($widthMatch[1]);
            if (count($widths) < 3) {
                continue;
            }
            $widths = array_slice($widths, 0, 3);
            $entryWidth = array_sum($widths);
            if ($entryWidth <= 0) {
                continue;
            }

            $decoded = $this->decodeStreamObject($body, $objects);
            if ($decoded === null) {
                continue;
            }

            $offset = 0;
            foreach ($this->xrefIndexRanges($body) as $range) {
                [$startObject, $count] = $range;
                for ($index = 0; $index < $count; $index++) {
                    if ($offset + $entryWidth > strlen($decoded)) {
                        break 2;
                    }

                    $fieldOffset = $offset;
                    $type = $widths[0] === 0 ? 1 : $this->xrefFieldValue($decoded, $fieldOffset, $widths[0]);
                    $fieldTwo = $this->xrefFieldValue($decoded, $fieldOffset, $widths[1]);
                    $fieldThree = $this->xrefFieldValue($decoded, $fieldOffset, $widths[2]);
                    $objectNumber = $startObject + $index;
                    if ($type === 0) {
                        $entries[$objectNumber] = [
                            'type' => 0,
                            'generation' => $fieldThree,
                            'offset' => $fieldTwo,
                        ];
                    } elseif ($type === 1) {
                        $entries[$objectNumber] = [
                            'type' => 1,
                            'offset' => $fieldTwo,
                            'generation' => $fieldThree,
                        ];
                    } elseif ($type === 2 && $fieldTwo > 0) {
                        $entries[$objectNumber] = [
                            'type' => 2,
                            'objectStream' => $fieldTwo,
                            'index' => $fieldThree,
                        ];
                    }

                    $offset += $entryWidth;
                }
            }
        }

        return $entries;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function xrefIndexRanges(string $xrefBody): array
    {
        if (preg_match('/\/Index\s*\[(.*?)\]/s', $xrefBody, $match) === 1) {
            $values = $this->integersFromPdfArray($match[1]);
            $ranges = [];
            for ($index = 0, $count = count($values); $index + 1 < $count; $index += 2) {
                $ranges[] = [max(0, $values[$index]), max(0, $values[$index + 1])];
            }

            return $ranges;
        }

        if (preg_match('/\/Size\s+(\d+)/', $xrefBody, $match) === 1) {
            return [[0, max(0, (int) $match[1])]];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    private function integersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/-?\d+/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    private function xrefFieldValue(string $bytes, int &$offset, int $width): int
    {
        $value = 0;
        for ($index = 0; $index < $width; $index++) {
            $value = ($value << 8) + ord($bytes[$offset + $index]);
        }
        $offset += $width;

        return $value;
    }

    /**
     * @return array<string, string>
     * @param array<int, string> $objects
     */
    private function namedCMapBodies(array $objects): array
    {
        $named = [];
        foreach ($objects as $body) {
            $cmap = $this->decodedCMapBody($body, $objects);
            if ($cmap === null || !preg_match('/\/CMapName\s+\/([^\s\[\]()<>{}\/%]+)\s+def\b/s', $cmap, $match)) {
                continue;
            }

            $named[$this->decodePdfName($match[1])] = $cmap;
        }

        return $named;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     * @param array<int, string> $objects
     * @param array<string, string> $namedCMapBodies
     */
    private function toUnicodeMapFromObject(string $objectBody, array $objects, array $namedCMapBodies): ?array
    {
        $decoded = $this->decodedCMapBody($objectBody, $objects);
        if ($decoded === null) {
            return null;
        }

        return $this->parseToUnicodeCMap($decoded, $namedCMapBodies);
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodedCMapBody(string $objectBody, array $objects): ?string
    {
        if (!preg_match('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $objectBody, $match)) {
            return null;
        }

        return $this->decodeStream($match[1], $match[2], $objects);
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     * @param array<string, string> $namedCMapBodies
     * @param list<string> $seenCMaps
     */
    private function parseToUnicodeCMap(string $cmap, array $namedCMapBodies = [], array $seenCMaps = []): array
    {
        $map = [];
        $codeSpaceRanges = [];
        $writingMode = null;

        if (preg_match_all('/\/([^\s\[\]()<>{}\/%]+)\s+usecmap\b/s', $cmap, $useCMapMatches)) {
            foreach ($useCMapMatches[1] as $rawName) {
                $name = $this->decodePdfName($rawName);
                if (in_array($name, $seenCMaps, true) || !isset($namedCMapBodies[$name])) {
                    continue;
                }

                $base = $this->parseToUnicodeCMap($namedCMapBodies[$name], $namedCMapBodies, [...$seenCMaps, $name]);
                $map = $base['map'] + $map;
                if (isset($base['writingMode'])) {
                    $writingMode = (int) $base['writingMode'] === 1 ? 1 : 0;
                }
                foreach ($base['codeSpaceRanges'] as $range) {
                    $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
                }
            }
        }

        if (preg_match('/\/WMode\s+([01])\s+def\b/s', $cmap, $wModeMatch) === 1) {
            $writingMode = (int) $wModeMatch[1] === 1 ? 1 : 0;
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($entries as $entry) {
                    $source = $this->normalizeHexKey($entry[1]);
                    if ($source !== '') {
                        $map[$source] = $this->decodeCMapUnicodeHex($entry[2]);
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                $this->parseToUnicodeRanges($block, $map);
            }
        }

        foreach ($this->parseCMapCodeSpaceRanges($cmap) as $range) {
            $codeSpaceRanges[$range['start'] . ':' . $range['end'] . ':' . $range['width']] = $range;
        }
        $codeSpaceRanges = array_values($codeSpaceRanges);
        usort($codeSpaceRanges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        $result = [
            'map' => $map,
            'codeSpaceRanges' => $codeSpaceRanges,
        ];
        if ($writingMode !== null) {
            $result['writingMode'] = $writingMode;
        }

        return $result;
    }

    /**
     * @param array<string, string> $map
     */
    private function parseToUnicodeRanges(string $block, array &$map): void
    {
        if (preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*\[(.*?)\]/s', $block, $arrayRanges, PREG_SET_ORDER)) {
            foreach ($arrayRanges as $range) {
                $start = $this->normalizeHexKey($range[1]);
                $end = $this->normalizeHexKey($range[2]);
                if ($start === '' || $end === '') {
                    continue;
                }

                preg_match_all('/<([\da-fA-F\s]+)>/s', $range[3], $targets);
                if (($targets[1] ?? []) === []) {
                    continue;
                }

                $source = hexdec($start);
                $last = hexdec($end);
                $sourceWidth = strlen($start);
                foreach ($targets[1] as $target) {
                    if ($source > $last) {
                        break;
                    }

                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $map[$sourceKey] = $this->decodeCMapUnicodeHex($target);
                    $source++;
                }
            }
        }

        if (preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $ranges, PREG_SET_ORDER)) {
            foreach ($ranges as $range) {
                $start = $this->normalizeHexKey($range[1]);
                $end = $this->normalizeHexKey($range[2]);
                $target = $this->normalizeHexKey($range[3]);
                if ($start === '' || $end === '' || $target === '') {
                    continue;
                }

                $source = hexdec($start);
                $last = hexdec($end);
                $targetCode = hexdec($target);
                $sourceWidth = strlen($start);
                $targetWidth = strlen($target);
                $count = 0;
                while ($source <= $last && $count < 512) {
                    $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                    $targetHex = str_pad(strtolower(dechex($targetCode + $count)), $targetWidth, '0', STR_PAD_LEFT);
                    $map[$sourceKey] = $this->decodeCMapUnicodeHex($targetHex);
                    $source++;
                    $count++;
                }
            }
        }
    }

    /**
     * @return list<array{start: int, end: int, width: int}>
     */
    private function parseCMapCodeSpaceRanges(string $cmap): array
    {
        $ranges = [];
        if (!preg_match_all('/begincodespacerange(.*?)endcodespacerange/s', $cmap, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (!preg_match_all('/<([\da-fA-F\s]+)>\s*<([\da-fA-F\s]+)>/s', $block, $entries, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($entries as $entry) {
                $start = $this->normalizeHexKey($entry[1]);
                $end = $this->normalizeHexKey($entry[2]);
                if ($start === '' || $end === '' || strlen($start) !== strlen($end) || strlen($start) > 8) {
                    continue;
                }

                $startValue = hexdec($start);
                $endValue = hexdec($end);
                if ($startValue > $endValue) {
                    continue;
                }

                $ranges[$start . ':' . $end] = [
                    'start' => $startValue,
                    'end' => $endValue,
                    'width' => strlen($start),
                ];
            }
        }

        $ranges = array_values($ranges);
        usort($ranges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return $ranges;
    }

    private function normalizeHexKey(string $hex): string
    {
        $normalized = preg_replace('/\s+/', '', strtolower($hex));
        if ($normalized === null || $normalized === '' || preg_match('/^[\da-f]+$/', $normalized) !== 1) {
            return '';
        }
        if (strlen($normalized) % 2 === 1) {
            $normalized = '0' . $normalized;
        }

        return $normalized;
    }

    private function decodeCMapUnicodeHex(string $hex): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        if (strlen($normalized) % 4 === 0) {
            $bytes = hex2bin($normalized);
            if ($bytes !== false) {
                $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', $bytes);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return $this->decodeHexString($normalized);
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function textRunsFromContentStream(string $stream, array $fontToUnicodeMaps, array $markedContentProperties = []): array
    {
        $runs = [];
        $operands = [];
        $currentFontResource = null;
        $markedContentStack = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $replacementIndex = $this->activeMarkedContentReplacementIndex($markedContentStack);
                    if ($replacementIndex !== null) {
                        if (!$markedContentStack[$replacementIndex]['emitted']) {
                            $runs[] = $markedContentStack[$replacementIndex]['replacement'];
                            $markedContentStack[$replacementIndex]['emitted'] = true;
                        }
                    } else {
                        $runs[] = $this->decodeTextOperand($operand, $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource));
                    }
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $markedContentStack[] = [
                    'replacement' => null,
                    'emitted' => true,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BDC') {
                $markedContentStack[] = [
                    'replacement' => $this->markedContentReplacementOperand($operands, $markedContentProperties),
                    'emitted' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $markedContent = array_pop($markedContentStack);
                if (
                    is_array($markedContent)
                    && $markedContent['replacement'] !== null
                    && !$markedContent['emitted']
                    && $this->activeMarkedContentReplacementIndex($markedContentStack) === null
                ) {
                    $runs[] = $markedContent['replacement'];
                }
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $runs;
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function textLinesFromContentStream(string $stream, array $fontToUnicodeMaps, array $markedContentProperties = []): array
    {
        $lines = [];
        $operands = [];
        $currentLine = '';
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $currentTextEndY = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextMatrixHorizontalScale = 1.0;
        $pendingPositionWordGap = false;
        $textStateStack = [];
        $markedContentStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushLine($lines, $currentLine);
                    $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                    $currentTextEndX = $currentTextX;
                    $currentTextEndY = $currentTextY;
                    $pendingPositionWordGap = false;
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $replacementIndex = $this->activeMarkedContentReplacementIndex($markedContentStack);
                    if ($replacementIndex !== null) {
                        $decoded = $markedContentStack[$replacementIndex]['emitted']
                            ? ''
                            : $markedContentStack[$replacementIndex]['replacement'];
                        $markedContentStack[$replacementIndex]['emitted'] = true;
                    } else {
                        $decoded = $this->decodeTextOperand($operand, $toUnicodeMap);
                    }
                    $this->appendPositionedText($currentLine, $decoded, $pendingPositionWordGap);
                    if ($this->mapWritingMode($toUnicodeMap) === 1) {
                        $currentTextEndY = $this->advanceTextEndYForOperand(
                            $currentTextEndY ?? $currentTextY,
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing
                        );
                    } else {
                        $currentTextEndX = $this->advanceTextEndXForOperand(
                            $currentTextEndX ?? $currentTextX,
                            $operand,
                            $toUnicodeMap,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale * $currentTextMatrixHorizontalScale
                        );
                    }
                }
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $markedContentStack[] = [
                    'replacement' => null,
                    'emitted' => true,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'BDC') {
                $markedContentStack[] = [
                    'replacement' => $this->markedContentReplacementOperand($operands, $markedContentProperties),
                    'emitted' => false,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                $markedContent = array_pop($markedContentStack);
                if (
                    is_array($markedContent)
                    && $markedContent['replacement'] !== null
                    && !$markedContent['emitted']
                    && $this->activeMarkedContentReplacementIndex($markedContentStack) === null
                ) {
                    $this->appendPositionedText($currentLine, $markedContent['replacement'], $pendingPositionWordGap);
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
                    'fontResource' => $currentFontResource,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontSize = $state['fontSize'];
                    $currentFontResource = $state['fontResource'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                if ($this->textMoveBreaksLine($operands)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                } elseif ($this->textMoveCreatesWordGap($operands)) {
                    $pendingPositionWordGap = $currentLine !== '';
                }
                $currentTextX = $this->textMoveX($operands, $currentTextX);
                $currentTextY = $this->textMoveY($operands, $currentTextY);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                if ($this->mapWritingMode($toUnicodeMap) === 1) {
                    if ($this->verticalTextMatrixBreaksLine($operands, $currentTextX)) {
                        $this->pushLine($lines, $currentLine);
                        $pendingPositionWordGap = false;
                    } elseif ($this->verticalTextMatrixCreatesWordGap($operands, $currentTextEndY)) {
                        $pendingPositionWordGap = $currentLine !== '';
                    }
                } elseif ($this->textMatrixBreaksLine($operands, $currentTextY)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                } elseif ($this->textMatrixCreatesWordGap($operands, $currentTextEndX)) {
                    $pendingPositionWordGap = $currentLine !== '';
                }
                $currentTextX = $this->textMatrixX($operands);
                $currentTextY = $this->textMatrixY($operands);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $currentTextMatrixHorizontalScale = $this->textMatrixHorizontalScale($operands) ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $this->pushLine($lines, $currentLine);
                $currentTextY = $this->advanceTextYByLeading($currentTextY, $currentTextLeading);
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextMatrixHorizontalScale = 1.0;
                $pendingPositionWordGap = false;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        $this->pushLine($lines, $currentLine);

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function contentTokens(string $stream): array
    {
        $tokens = [];
        $length = strlen($stream);
        $index = 0;

        while ($index < $length) {
            $char = $stream[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($stream, $index);
                continue;
            }

            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $tokens[] = $this->readHexToken($stream, $index);
                continue;
            }

            if ($char === '<' && $index + 1 < $length && $stream[$index + 1] === '<') {
                $tokens[] = $this->readDictionaryToken($stream, $index);
                continue;
            }

            if ($char === '[') {
                $tokens[] = $this->readArrayToken($stream, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($stream[$index])) {
                $index++;
            }
            if ($index === $start) {
                $index++;
                continue;
            }
            $token = substr($stream, $start, $index - $start);
            if ($token === 'BI') {
                $this->skipInlineImage($stream, $index);
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function skipInlineImage(string $stream, int &$index): void
    {
        $length = strlen($stream);
        $foundImageData = false;

        while ($index < $length) {
            $this->skipContentWhitespaceAndComments($stream, $index);
            if ($index >= $length) {
                break;
            }

            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === '[') {
                $this->readArrayToken($stream, $index);
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($stream[$index])) {
                $index++;
            }

            if (substr($stream, $start, $index - $start) === 'ID') {
                $foundImageData = true;
                break;
            }

            if ($index === $start) {
                $index++;
            }
        }

        if (!$foundImageData) {
            $index = $length;
            return;
        }

        $this->consumeInlineImageDataPrefixWhitespace($stream, $index);
        while ($index < $length) {
            $end = strpos($stream, 'EI', $index);
            if ($end === false) {
                $index = $length;
                return;
            }

            if ($this->inlineImageEndMarkerAt($stream, $end)) {
                $index = $end + 2;
                return;
            }

            $index = $end + 2;
        }
    }

    private function skipContentWhitespaceAndComments(string $stream, int &$index): void
    {
        $length = strlen($stream);
        while ($index < $length) {
            if (ctype_space($stream[$index])) {
                $index++;
                continue;
            }

            if ($stream[$index] !== '%') {
                return;
            }

            while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                $index++;
            }
        }
    }

    private function consumeInlineImageDataPrefixWhitespace(string $stream, int &$index): void
    {
        $length = strlen($stream);
        if ($index >= $length || !ctype_space($stream[$index])) {
            return;
        }

        if ($stream[$index] === "\r") {
            $index++;
            if ($index < $length && $stream[$index] === "\n") {
                $index++;
            }
            return;
        }

        $index++;
    }

    private function inlineImageEndMarkerAt(string $stream, int $offset): bool
    {
        if ($offset <= 0 || !ctype_space($stream[$offset - 1])) {
            return false;
        }

        $after = $offset + 2;
        return $after >= strlen($stream) || $this->isDelimiter($stream[$after]);
    }

    private function readLiteralToken(string $stream, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($stream);

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readHexToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length && $stream[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readDictionaryToken(string $stream, int &$index): string
    {
        $body = $this->readPdfDictionaryTokenAt($stream, $index);
        if ($body === null) {
            $index += 2;
            return '<<';
        }

        return '<<' . $body . '>>';
    }

    private function readArrayToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === ']') {
                $index++;
                break;
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}%', $char);
    }

    /**
     * @param list<string> $operands
     */
    private function textShowingOperand(string $operator, array $operands): ?string
    {
        if ($operator === '"') {
            for ($index = count($operands) - 1; $index >= 0; $index--) {
                if ($this->isTextOperand($operands[$index])) {
                    return $operands[$index];
                }
            }

            return null;
        }

        $operand = end($operands);
        return is_string($operand) && $this->isTextOperand($operand) ? $operand : null;
    }

    private function isTextShowingOperator(string $token): bool
    {
        return in_array($token, ['Tj', 'TJ', "'", '"'], true);
    }

    /**
     * @param list<string> $operands
     * @param array<string, array{actualText: string|null, altText: string|null}> $markedContentProperties
     */
    private function markedContentReplacementOperand(array $operands, array $markedContentProperties): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $propertyOperand = trim((string) $operands[count($operands) - 1]);
        if (str_starts_with($propertyOperand, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($propertyOperand, 0);
            if ($dictionary === null) {
                return null;
            }

            return $this->markedContentReplacementFromProperty([
                'actualText' => $this->pdfOptionalStringValueAfterName($dictionary, 'ActualText', []),
                'altText' => $this->pdfOptionalStringValueAfterName($dictionary, 'Alt', []),
            ]);
        }

        if (!str_starts_with($propertyOperand, '/')) {
            return null;
        }

        $resourceName = $this->decodePdfName(substr($propertyOperand, 1));
        return isset($markedContentProperties[$resourceName])
            ? $this->markedContentReplacementFromProperty($markedContentProperties[$resourceName])
            : null;
    }

    /**
     * @param array{actualText: string|null, altText: string|null} $property
     */
    private function markedContentReplacementFromProperty(array $property): ?string
    {
        if ($property['actualText'] !== null) {
            return $property['actualText'];
        }

        return $property['altText'];
    }

    /**
     * @param list<array{replacement: string|null, emitted: bool}> $markedContentStack
     */
    private function activeMarkedContentReplacementIndex(array $markedContentStack): ?int
    {
        foreach ($markedContentStack as $index => $markedContent) {
            if ($markedContent['replacement'] !== null) {
                return $index;
            }
        }

        return null;
    }

    private function isTextOperand(string $token): bool
    {
        $token = ltrim($token);
        return str_starts_with($token, '(') || str_starts_with($token, '[') || preg_match('/^<[\da-fA-F\s]*>$/', $token) === 1;
    }

    private function isOperator(string $token): bool
    {
        return preg_match('/^[A-Za-z*"\']+$/', $token) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function fontResourceOperand(array $operands): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $operand = $operands[count($operands) - 2];
        if (!str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr(hexdec($match[1]));
        }, $name) ?? $name;
    }

    /**
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function currentToUnicodeMap(array $fontToUnicodeMaps, ?string $fontResource): ?array
    {
        if ($fontResource !== null && isset($fontToUnicodeMaps[$fontResource])) {
            return $fontToUnicodeMaps[$fontResource];
        }

        return $fontToUnicodeMaps[''] ?? null;
    }

    private function mapWritingMode(?array $toUnicodeMap): int
    {
        $writingMode = $toUnicodeMap['writingMode'] ?? 0;

        return (int) $writingMode === 1 ? 1 : 0;
    }

    /**
     * @param list<string> $operands
     */
    private function fontSizeOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textLeadingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textCharacterSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textWordSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textHorizontalScaleOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteWordSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 3]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteCharacterSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveBreaksLine(array $operands): bool
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return true;
        }

        return abs($ty) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveCreatesWordGap(array $operands): bool
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return false;
        }

        return $tx >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveX(array $operands, ?float $currentTextX): ?float
    {
        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return null;
        }

        return $currentTextX === null ? $tx : $currentTextX + $tx;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveY(array $operands, ?float $currentTextY): ?float
    {
        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return null;
        }

        return $currentTextY === null ? $ty : $currentTextY + $ty;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandX(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandY(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixBreaksLine(array $operands, ?float $currentTextY): bool
    {
        $matrixY = $this->textMatrixY($operands);
        if ($matrixY === null || $currentTextY === null) {
            return true;
        }

        return abs($matrixY - $currentTextY) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixCreatesWordGap(array $operands, ?float $currentTextEndX): bool
    {
        $matrixX = $this->textMatrixX($operands);
        if ($matrixX === null || $currentTextEndX === null) {
            return false;
        }

        return $matrixX - $currentTextEndX >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMatrixBreaksLine(array $operands, ?float $currentTextX): bool
    {
        $matrixX = $this->textMatrixX($operands);
        if ($matrixX === null || $currentTextX === null) {
            return true;
        }

        return abs($matrixX - $currentTextX) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function verticalTextMatrixCreatesWordGap(array $operands, ?float $currentTextEndY): bool
    {
        $matrixY = $this->textMatrixY($operands);
        if ($matrixY === null || $currentTextEndY === null) {
            return false;
        }

        return abs($matrixY - $currentTextEndY) >= self::POSITIONED_TEXT_WORD_GAP;
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixX(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixY(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixHorizontalScale(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 6]);
    }

    private function advanceTextYByLeading(?float $currentTextY, ?float $currentTextLeading): ?float
    {
        if ($currentTextY === null || $currentTextLeading === null) {
            return null;
        }

        return $currentTextY - $currentTextLeading;
    }

    /**
     * @param list<string> $lines
     */
    private function pushLine(array &$lines, string &$currentLine): void
    {
        $line = rtrim($currentLine);
        if ($line !== '') {
            $lines[] = $line;
        }
        $currentLine = '';
    }

    private function appendPositionedText(string &$currentLine, string $decoded, bool &$pendingPositionWordGap): void
    {
        if ($decoded === '') {
            $pendingPositionWordGap = false;
            return;
        }

        if ($pendingPositionWordGap && !$this->endsWithWhitespace($currentLine) && !$this->startsWithWhitespace($decoded)) {
            $currentLine .= ' ';
        }

        $currentLine .= $decoded;
        $pendingPositionWordGap = false;
    }

    private function advanceTextEndX(
        ?float $currentTextEndX,
        string $decoded,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale,
        ?array $glyphWidths = null
    ): ?float {
        if ($currentTextEndX === null || $decoded === '') {
            return $currentTextEndX;
        }

        $fontSize ??= 12.0;
        $characters = $glyphWidths !== null && $glyphWidths !== [] ? count($glyphWidths) : $this->length($decoded);
        $baseAdvance = $glyphWidths !== null && $glyphWidths !== []
            ? (array_sum($glyphWidths) / 1000.0) * $fontSize
            : $characters * $fontSize * self::SIMPLE_TEXT_ADVANCE_RATIO;
        $spacingAdvance = (max(0, $characters - 1) * $characterSpacing) + (substr_count($decoded, ' ') * $wordSpacing);
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX + (($baseAdvance + $spacingAdvance) * $scale);
    }

    private function advanceTextEndXForOperand(
        ?float $currentTextEndX,
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale
    ): ?float {
        if ($currentTextEndX === null) {
            return null;
        }

        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            return $this->advanceTextEndX(
                $currentTextEndX,
                $this->decodeTextOperand($operand, $toUnicodeMap),
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $horizontalScale,
                $this->glyphWidthsForTextOperand($operand, $toUnicodeMap)
            );
        }

        $endX = $currentTextEndX;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $endX = $this->advanceTextEndX(
                    $endX,
                    $this->decodeTextOperand($element['value'], $toUnicodeMap),
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $horizontalScale,
                    $this->glyphWidthsForTextOperand((string) $element['value'], $toUnicodeMap)
                );
                continue;
            }

            $endX = $this->adjustTextEndX($endX, (float) $element['value'], $fontSize, $horizontalScale);
        }

        return $endX;
    }

    private function advanceTextEndY(
        ?float $currentTextEndY,
        string $decoded,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        ?array $glyphDisplacements = null
    ): ?float {
        if ($currentTextEndY === null || $decoded === '') {
            return $currentTextEndY;
        }

        $fontSize ??= 12.0;
        $characters = $glyphDisplacements !== null && $glyphDisplacements !== [] ? count($glyphDisplacements) : $this->length($decoded);
        $baseAdvance = $glyphDisplacements !== null && $glyphDisplacements !== []
            ? (array_sum($glyphDisplacements) / 1000.0) * $fontSize
            : -$characters * $fontSize;
        $spacingAdvance = (max(0, $characters - 1) * $characterSpacing) + (substr_count($decoded, ' ') * $wordSpacing);
        $direction = $baseAdvance < 0 ? -1.0 : 1.0;

        return $currentTextEndY + $baseAdvance + ($spacingAdvance * $direction);
    }

    private function advanceTextEndYForOperand(
        ?float $currentTextEndY,
        string $operand,
        ?array $toUnicodeMap,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing
    ): ?float {
        if ($currentTextEndY === null) {
            return null;
        }

        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            return $this->advanceTextEndY(
                $currentTextEndY,
                $this->decodeTextOperand($operand, $toUnicodeMap),
                $fontSize,
                $characterSpacing,
                $wordSpacing,
                $this->glyphVerticalDisplacementsForTextOperand($operand, $toUnicodeMap)
            );
        }

        $endY = $currentTextEndY;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $endY = $this->advanceTextEndY(
                    $endY,
                    $this->decodeTextOperand($element['value'], $toUnicodeMap),
                    $fontSize,
                    $characterSpacing,
                    $wordSpacing,
                    $this->glyphVerticalDisplacementsForTextOperand((string) $element['value'], $toUnicodeMap)
                );
                continue;
            }

            $endY = $this->adjustTextEndY($endY, (float) $element['value'], $fontSize);
        }

        return $endY;
    }

    /**
     * @return list<float>|null
     */
    private function glyphWidthsForTextOperand(string $operand, ?array $toUnicodeMap): ?array
    {
        if ($toUnicodeMap === null) {
            return null;
        }

        $cidWidths = $toUnicodeMap['cidWidths'] ?? [];
        $defaultWidth = $toUnicodeMap['cidDefaultWidth'] ?? null;
        $cidSet = $toUnicodeMap['cidSet'] ?? null;
        if ((!is_array($cidWidths) || $cidWidths === []) && $defaultWidth === null && !is_array($cidSet)) {
            return null;
        }

        $hex = $this->textOperandSourceHex($operand);
        if ($hex === '') {
            return [];
        }

        $widths = [];
        foreach ($this->textOperandSourceKeys($hex, $toUnicodeMap) as $key) {
            $cid = hexdec($key);
            if (is_array($cidWidths) && array_key_exists($cid, $cidWidths)) {
                $widths[] = (float) $cidWidths[$cid];
                continue;
            }
            if (is_array($cidSet) && !isset($cidSet[$cid])) {
                $widths[] = 500.0;
                continue;
            }
            $widths[] = (float) ($defaultWidth ?? 500.0);
        }

        return $widths;
    }

    /**
     * @return list<float>|null
     */
    private function glyphVerticalDisplacementsForTextOperand(string $operand, ?array $toUnicodeMap): ?array
    {
        if ($toUnicodeMap === null || $this->mapWritingMode($toUnicodeMap) !== 1) {
            return null;
        }

        $cidDisplacements = $toUnicodeMap['cidVerticalDisplacements'] ?? [];
        $defaultDisplacement = $toUnicodeMap['cidDefaultVerticalDisplacement'] ?? -1000.0;

        $hex = $this->textOperandSourceHex($operand);
        if ($hex === '') {
            return [];
        }

        $displacements = [];
        foreach ($this->textOperandSourceKeys($hex, $toUnicodeMap) as $key) {
            $cid = hexdec($key);
            $displacements[] = is_array($cidDisplacements) && array_key_exists($cid, $cidDisplacements)
                ? (float) $cidDisplacements[$cid]
                : (float) $defaultDisplacement;
        }

        return $displacements;
    }

    private function textOperandSourceHex(string $operand): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '<')) {
            return $this->normalizeHexKey(trim($operand, '<>'));
        }

        if (str_starts_with($operand, '(')) {
            return bin2hex($this->decodeLiteralString(substr($operand, 1, -1)));
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function textOperandSourceKeys(string $hex, array $toUnicodeMap): array
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return [];
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $keyLengths = is_array($mappings)
            ? array_values(array_unique(array_map('strlen', array_keys($mappings))))
            : [];
        rsort($keyLengths, SORT_NUMERIC);

        $keys = [];
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $sourceLength = $this->toUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                is_array($mappings) ? $mappings : [],
                $normalized,
                $offset
            );
            $keys[] = substr($normalized, $offset, $sourceLength);
            $offset += $sourceLength;
        }

        return $keys;
    }

    private function adjustTextEndX(?float $currentTextEndX, float $adjustment, ?float $fontSize, float $horizontalScale): ?float
    {
        if ($currentTextEndX === null) {
            return null;
        }

        $fontSize ??= 12.0;
        $scale = $horizontalScale / 100.0;

        return $currentTextEndX - (($adjustment / 1000.0) * $fontSize * $scale);
    }

    private function adjustTextEndY(?float $currentTextEndY, float $adjustment, ?float $fontSize): ?float
    {
        if ($currentTextEndY === null) {
            return null;
        }

        $fontSize ??= 12.0;

        return $currentTextEndY - (($adjustment / 1000.0) * $fontSize);
    }

    /**
     * @return list<array{type: string, value: string|float}>
     */
    private function textArrayElements(string $operand): array
    {
        $operand = trim($operand);
        $body = substr($operand, 1, -1);
        $elements = [];
        $index = 0;
        $length = strlen($body);

        while ($index < $length) {
            if (ctype_space($body[$index])) {
                $index++;
                continue;
            }

            if ($body[$index] === '(') {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readLiteralToken($body, $index),
                ];
                continue;
            }

            if ($body[$index] === '<' && ($index + 1 >= $length || $body[$index + 1] !== '<')) {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readHexToken($body, $index),
                ];
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($body[$index]) && !str_contains('[]()<>{}%', $body[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $token = substr($body, $start, $index - $start);
            $adjustment = $this->numericOperand($token);
            if ($adjustment !== null) {
                $elements[] = [
                    'type' => 'adjustment',
                    'value' => $adjustment,
                ];
            }
        }

        return $elements;
    }

    private function startsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space($text[0]);
    }

    private function endsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space(substr($text, -1));
    }

    private function numericOperand(string $operand): ?float
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $operand) !== 1) {
            return null;
        }

        return (float) $operand;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null $toUnicodeMap
     */
    private function decodeTextOperand(string $operand, ?array $toUnicodeMap = null): string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '[')) {
            $text = '';
            foreach ($this->textArrayElements($operand) as $element) {
                if ($element['type'] === 'text') {
                    $text .= $this->decodeTextOperand((string) $element['value'], $toUnicodeMap);
                }
            }
            return $text;
        }
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            if ($toUnicodeMap !== null) {
                return $this->decodeHexStringWithToUnicodeMap($hex, $toUnicodeMap);
            }
            return $this->decodeHexString($hex);
        }

        $decoded = $this->decodeLiteralString(substr($operand, 1, -1));
        if ($toUnicodeMap !== null) {
            return $this->decodeHexStringWithToUnicodeMap(bin2hex($decoded), $toUnicodeMap);
        }

        return $this->decodePdfStringBytes($decoded);
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>} $toUnicodeMap
     */
    private function decodeHexStringWithToUnicodeMap(string $hex, array $toUnicodeMap): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($mappings))));
        rsort($keyLengths, SORT_NUMERIC);
        if ($keyLengths === [] && ($toUnicodeMap['codeSpaceRanges'] ?? []) === []) {
            return $this->decodeHexString($normalized);
        }

        $text = '';
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $sourceLength = $this->toUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $toUnicodeMap['codeSpaceRanges'] ?? [],
                $mappings,
                $normalized,
                $offset
            );
            $key = substr($normalized, $offset, $sourceLength);
            $text .= array_key_exists($key, $mappings)
                ? $mappings[$key]
                : $this->decodeUnmappedToUnicodeSource($key);
            $offset += $sourceLength;
        }

        return $text;
    }

    /**
     * @param list<int> $keyLengths
     * @param list<array{start: int, end: int, width: int}> $codeSpaceRanges
     * @param array<string, string> $mappings
     */
    private function toUnicodeSourceLength(
        array $keyLengths,
        int $remainingHexLength,
        array $codeSpaceRanges,
        array $mappings,
        string $normalized,
        int $offset
    ): int {
        foreach ($codeSpaceRanges as $range) {
            $width = $range['width'];
            if ($width <= 0 || $width > $remainingHexLength) {
                continue;
            }

            $source = hexdec(substr($normalized, $offset, $width));
            if ($source >= $range['start'] && $source <= $range['end']) {
                return $width;
            }
        }

        foreach ($keyLengths as $keyLength) {
            if ($keyLength <= 0 || $keyLength > $remainingHexLength) {
                continue;
            }

            if (array_key_exists(substr($normalized, $offset, $keyLength), $mappings)) {
                return $keyLength;
            }
        }

        $usableLengths = array_values(array_filter(
            $keyLengths,
            static fn (int $keyLength): bool => $keyLength > 0 && $keyLength <= $remainingHexLength
        ));
        rsort($usableLengths, SORT_NUMERIC);

        return $usableLengths[0] ?? min(2, max(1, $remainingHexLength));
    }

    private function decodeUnmappedToUnicodeSource(string $hex): string
    {
        if ($hex === '') {
            return '';
        }

        $decoded = $this->decodeCMapUnicodeHex($hex);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $decoded) ?? $decoded;
    }

    private function decodeHexString(string $hex): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        return $this->decodePdfStringBytes($bytes);
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
