<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAnnotationExtractor
{
    private const BORDER_STYLE_NAMES = [
        'S' => 'solid',
        'D' => 'dashed',
        'B' => 'beveled',
        'I' => 'inset',
        'U' => 'underline',
    ];

    /**
     * Native boundary for PDF page /Annots presentation metadata.
     *
     * @return list<array{pnum: int, page_object: int, annotations: list<array<string, mixed>>}>
     */
    public function extractPageAnnotations(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pages = [];

        foreach ($pageObjectNumbers as $pnum => $pageObjectNumber) {
            $pageBody = $objects[$pageObjectNumber] ?? null;
            if ($pageBody === null) {
                continue;
            }

            $records = $this->annotationRecordsForPage($pageBody, $objects);
            if ($records === []) {
                continue;
            }

            $reversePopups = $this->popupRecordsByParentObject($records);
            $annotations = [];
            foreach ($records as $record) {
                $subtype = $this->subtypeFromAnnotation($record['body']);
                if ($subtype === 'Popup' && $this->objectReferenceValueAfterName($record['body'], 'Parent') !== null) {
                    continue;
                }

                $annotations[] = $this->annotationReviewRow($record, $objects, $reversePopups);
            }

            if ($annotations !== []) {
                $pages[] = [
                    'pnum' => $pnum,
                    'page_object' => $pageObjectNumber,
                    'annotations' => $annotations,
                ];
            }
        }

        return $pages;
    }

    /**
     * @param array{body: string, object: int|null} $record
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>
     */
    private function annotationReviewRow(array $record, array $objects, array $reversePopups): array
    {
        $body = $record['body'];
        $subtype = $this->subtypeFromAnnotation($body);
        $rect = $this->rectFromAnnotation($body);

        $row = [
            'subtype' => $subtype,
            'annotation_object' => $record['object'],
            'rect' => $rect,
            'contents' => $this->pdfStringValueAfterName($body, 'Contents', $objects),
            'title' => $this->pdfStringValueAfterName($body, 'T', $objects),
            'name' => $this->pdfStringValueAfterName($body, 'NM', $objects),
            'modified_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'border_color' => $this->colorValueAfterName($body, 'C', $objects),
            'interior_color' => $this->colorValueAfterName($body, 'IC', $objects),
            'opacity' => $this->opacityFromAnnotation($body, $objects),
            'border' => $this->borderFromAnnotation($body, $objects),
            'popup' => $this->popupFromAnnotation($body, $objects, $record['object'], $reversePopups),
        ];

        $geometry = $this->geometryFromAnnotation($body, $objects, $subtype, $rect);
        if ($geometry !== null) {
            $row['geometry'] = $geometry;
        }

        return $row;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsForPage(string $pageBody, array $objects): array
    {
        $annots = $this->valueAfterName($pageBody, 'Annots');
        if ($annots === null) {
            return [];
        }

        return $this->annotationRecordsFromValue($annots, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody($body, $objects);
        }

        if (str_starts_with($value, '<<')) {
            $dictionary = $this->readPdfDictionaryAt($value, 0);
            return $dictionary === null ? [] : [['body' => $dictionary, 'object' => null]];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return [];
        }

        $objectNumber = (int) $match[1];
        $objectBody = trim($objects[$objectNumber] ?? '');
        if ($objectBody === '') {
            return [];
        }

        if (str_starts_with($objectBody, '[')) {
            $body = $this->arrayBodyFromValue($objectBody);
            return $body === null ? [] : $this->annotationRecordsFromArrayBody($body, $objects);
        }

        $dictionary = $this->dictionaryObjectBody($objectBody);
        return $dictionary === null ? [] : [['body' => $dictionary, 'object' => $objectNumber]];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function annotationRecordsFromArrayBody(string $body, array $objects): array
    {
        $records = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionary = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                if ($dictionary === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $records[] = ['body' => $dictionary, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionary = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionary !== null) {
                    $records[] = ['body' => $dictionary, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $records;
    }

    /**
     * @param list<array{body: string, object: int|null}> $records
     * @return array<int, array{body: string, object: int|null}>
     */
    private function popupRecordsByParentObject(array $records): array
    {
        $popups = [];
        foreach ($records as $record) {
            if ($this->subtypeFromAnnotation($record['body']) !== 'Popup') {
                continue;
            }

            $parentObject = $this->objectReferenceValueAfterName($record['body'], 'Parent');
            if ($parentObject === null || isset($popups[$parentObject])) {
                continue;
            }

            $popups[$parentObject] = $record;
        }

        return $popups;
    }

    private function subtypeFromAnnotation(string $body): string
    {
        return $this->pdfNameValueAfterName($body, 'Subtype') ?? 'Unknown';
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, array{body: string, object: int|null}> $reversePopups
     * @return array<string, mixed>|null
     */
    private function popupFromAnnotation(string $body, array $objects, ?int $annotationObject, array $reversePopups): ?array
    {
        $record = null;
        $popup = $this->valueAfterName($body, 'Popup');
        if ($popup !== null) {
            $record = $this->resolvedDictionaryFromValue($popup, $objects);
        }

        if ($record === null && $annotationObject !== null) {
            $record = $reversePopups[$annotationObject] ?? null;
        }

        if ($record === null) {
            return null;
        }

        return [
            'object' => $record['object'],
            'rect' => $this->rectFromAnnotation($record['body']),
            'open' => $this->boolValueAfterName($record['body'], 'Open'),
            'parent_object' => $this->objectReferenceValueAfterName($record['body'], 'Parent'),
            'contents' => $this->pdfStringValueAfterName($record['body'], 'Contents', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function borderFromAnnotation(string $body, array $objects): ?array
    {
        $bs = $this->valueAfterName($body, 'BS');
        if ($bs !== null) {
            $dictionary = $this->resolvedDictionaryFromValue($bs, $objects);
            if ($dictionary !== null) {
                $width = $this->floatValueAfterName($dictionary['body'], 'W', $objects) ?? 1.0;
                $styleCode = $this->pdfNameValueAfterName($dictionary['body'], 'S') ?? 'S';
                $dashPattern = $this->numberArrayValueAfterName($dictionary['body'], 'D', $objects);

                return [
                    'source' => 'BS',
                    'width' => $width,
                    'style' => $width <= 0.0 ? 'none' : (self::BORDER_STYLE_NAMES[$styleCode] ?? strtolower($styleCode)),
                    'style_code' => $styleCode,
                    'dash_pattern' => $dashPattern,
                    'horizontal_corner_radius' => null,
                    'vertical_corner_radius' => null,
                ];
            }
        }

        $border = $this->valueAfterName($body, 'Border');
        if ($border === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($border, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 3) {
            return null;
        }

        $dashPattern = $this->dashPatternFromBorderArrayBody($arrayBody);
        $width = (float) $numbers[2];

        return [
            'source' => 'Border',
            'width' => $width,
            'style' => $width <= 0.0 ? 'none' : ($dashPattern === [] ? 'solid' : 'dashed'),
            'style_code' => $dashPattern === [] ? 'S' : 'D',
            'dash_pattern' => $dashPattern,
            'horizontal_corner_radius' => (float) $numbers[0],
            'vertical_corner_radius' => (float) $numbers[1],
        ];
    }

    /**
     * @return list<float>
     */
    private function dashPatternFromBorderArrayBody(string $arrayBody): array
    {
        $offset = 0;
        while (($start = strpos($arrayBody, '[', $offset)) !== false) {
            $endOffset = null;
            $body = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($body === null || $endOffset === null) {
                break;
            }

            return $this->numbersFromPdfArray($body);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array{space: string, components: list<float>, hex: string|null}|null
     */
    private function colorValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $components = array_map(fn (float $component): float => $this->clamp($component), $this->numbersFromPdfArray($arrayBody));
        $count = count($components);
        if ($count === 0) {
            return [
                'space' => 'transparent',
                'components' => [],
                'hex' => null,
            ];
        }

        if ($count === 1) {
            $rgb = [$components[0], $components[0], $components[0]];

            return [
                'space' => 'DeviceGray',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        if ($count === 3) {
            return [
                'space' => 'DeviceRGB',
                'components' => $components,
                'hex' => $this->rgbHex($components),
            ];
        }

        if ($count === 4) {
            [$c, $m, $y, $k] = $components;
            $rgb = [
                (1.0 - $c) * (1.0 - $k),
                (1.0 - $m) * (1.0 - $k),
                (1.0 - $y) * (1.0 - $k),
            ];

            return [
                'space' => 'DeviceCMYK',
                'components' => $components,
                'hex' => $this->rgbHex($rgb),
            ];
        }

        return [
            'space' => 'DeviceN',
            'components' => $components,
            'hex' => null,
        ];
    }

    private function opacityFromAnnotation(string $body, array $objects): ?float
    {
        $opacity = $this->floatValueAfterName($body, 'CA', $objects);

        return $opacity === null ? null : $this->clamp($opacity);
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function geometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        return match ($subtype) {
            'Line' => $this->lineGeometryFromAnnotation($body, $objects),
            'Ink' => $this->inkGeometryFromAnnotation($body, $objects),
            'Polygon', 'PolyLine' => $this->verticesGeometryFromAnnotation($body, $objects, $subtype),
            'Square', 'Circle' => $this->rectShapeGeometryFromAnnotation($body, $objects, $subtype, $rect),
            'FreeText' => $this->freeTextGeometryFromAnnotation($body, $objects, $rect),
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function lineGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $points = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'L', $objects), 2);
        if (count($points) !== 2) {
            return null;
        }

        return [
            'type' => 'line',
            'points' => $points,
            'bbox' => $this->bboxFromPoints($points),
            'line_endings' => $this->nameArrayValueAfterName($body, 'LE', $objects),
            'leader_line_length' => $this->floatValueAfterName($body, 'LL', $objects),
            'leader_line_extension' => $this->floatValueAfterName($body, 'LLE', $objects),
            'caption' => $this->boolValueAfterName($body, 'Cap'),
            'intent' => $this->pdfNameValueAfterName($body, 'IT'),
            'caption_offset' => $this->numberArrayValueAfterName($body, 'CO', $objects),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function inkGeometryFromAnnotation(string $body, array $objects): ?array
    {
        $value = $this->valueAfterName($body, 'InkList');
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody === null) {
            return null;
        }

        $paths = [];
        $offset = 0;
        $length = strlen($arrayBody);
        while ($offset < $length) {
            $start = strpos($arrayBody, '[', $offset);
            if ($start === false) {
                break;
            }

            $endOffset = null;
            $pathBody = $this->readPdfArrayAt($arrayBody, $start, $endOffset);
            if ($pathBody === null || $endOffset === null) {
                break;
            }

            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($pathBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
            $offset = $endOffset;
        }

        if ($paths === []) {
            $points = $this->pointsFromNumberArray($this->numbersFromPdfArray($arrayBody));
            if (count($points) >= 2) {
                $paths[] = $points;
            }
        }

        if ($paths === []) {
            return null;
        }

        $pathBboxes = array_map(fn (array $path): array => $this->bboxFromPoints($path), $paths);

        return [
            'type' => 'ink',
            'paths' => $paths,
            'path_bboxes' => $pathBboxes,
            'bbox' => $this->unionRects($pathBboxes),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function verticesGeometryFromAnnotation(string $body, array $objects, string $subtype): ?array
    {
        $vertices = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'Vertices', $objects));
        if (count($vertices) < 2) {
            return null;
        }

        $geometry = [
            'type' => $subtype === 'Polygon' ? 'polygon' : 'polyline',
            'vertices' => $vertices,
            'bbox' => $this->bboxFromPoints($vertices),
            'closed' => $subtype === 'Polygon',
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function rectShapeGeometryFromAnnotation(string $body, array $objects, string $subtype, ?array $rect): ?array
    {
        if ($rect === null) {
            return null;
        }

        $rectDifference = $this->rectDifferenceFromAnnotation($body, $objects);
        $shapeRect = $rectDifference === null ? $rect : $this->insetRect($rect, $rectDifference);
        $size = [
            max(0.0, $shapeRect[2] - $shapeRect[0]),
            max(0.0, $shapeRect[3] - $shapeRect[1]),
        ];

        $geometry = [
            'type' => strtolower($subtype),
            'rect' => $rect,
            'rect_difference' => $rectDifference,
            'shape_rect' => $shapeRect,
            'center' => [
                ($shapeRect[0] + $shapeRect[2]) / 2.0,
                ($shapeRect[1] + $shapeRect[3]) / 2.0,
            ],
            'size' => $size,
        ];

        if ($subtype === 'Circle') {
            $geometry['radii'] = [$size[0] / 2.0, $size[1] / 2.0];
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @param list<float>|null $rect
     * @return array<string, mixed>|null
     */
    private function freeTextGeometryFromAnnotation(string $body, array $objects, ?array $rect): ?array
    {
        $calloutLine = $this->pointsFromNumberArray($this->numberArrayValueAfterName($body, 'CL', $objects));
        if (count($calloutLine) < 2) {
            return null;
        }

        $geometry = [
            'type' => 'callout',
            'callout_line' => $calloutLine,
            'bbox' => $this->bboxFromPoints($calloutLine),
            'rect' => $rect,
            'rect_difference' => $this->rectDifferenceFromAnnotation($body, $objects),
            'elbow_point' => count($calloutLine) >= 3 ? $calloutLine[1] : null,
        ];

        $lineEndings = $this->nameArrayValueAfterName($body, 'LE', $objects);
        if ($lineEndings !== null) {
            $geometry['line_endings'] = $lineEndings;
        }

        return $geometry;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function rectDifferenceFromAnnotation(string $body, array $objects): ?array
    {
        $numbers = $this->numberArrayValueAfterName($body, 'RD', $objects);
        return $numbers !== null && count($numbers) >= 4 ? array_slice($numbers, 0, 4) : null;
    }

    /**
     * @param list<float> $rect
     * @param list<float> $difference
     * @return list<float>
     */
    private function insetRect(array $rect, array $difference): array
    {
        $shapeRect = [
            $rect[0] + $difference[0],
            $rect[1] + $difference[1],
            $rect[2] - $difference[2],
            $rect[3] - $difference[3],
        ];

        return $this->normalizeRect($shapeRect);
    }

    /**
     * @param list<float>|null $numbers
     * @return list<array{0: float, 1: float}>
     */
    private function pointsFromNumberArray(?array $numbers, ?int $limit = null): array
    {
        if ($numbers === null) {
            return [];
        }

        $points = [];
        for ($index = 0, $count = count($numbers) - 1; $index < $count; $index += 2) {
            $points[] = [(float) $numbers[$index], (float) $numbers[$index + 1]];
            if ($limit !== null && count($points) >= $limit) {
                break;
            }
        }

        return $points;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     * @return list<float>
     */
    private function bboxFromPoints(array $points): array
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return [min($xs), min($ys), max($xs), max($ys)];
    }

    /**
     * @param list<list<float>> $rects
     * @return list<float>
     */
    private function unionRects(array $rects): array
    {
        return [
            min(array_column($rects, 0)),
            min(array_column($rects, 1)),
            max(array_column($rects, 2)),
            max(array_column($rects, 3)),
        ];
    }

    /**
     * @param list<float> $components
     */
    private function rgbHex(array $components): string
    {
        $parts = [];
        foreach (array_slice($components, 0, 3) as $component) {
            $parts[] = str_pad(dechex((int) round($this->clamp($component) * 255)), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode('', $parts);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function numberArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $offset = 0;
        while (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $valueOffset = $match[0][1] + strlen($match[0][0]);
            $value = $this->valueStartingAtOffset($body, $valueOffset);
            if ($value === null) {
                return null;
            }

            $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
            if ($arrayBody !== null) {
                return $this->numbersFromPdfArray($arrayBody);
            }

            $offset = $valueOffset;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>|null
     */
    private function nameArrayValueAfterName(string $body, string $name, array $objects): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromPdfValue($value, $objects);
        if ($arrayBody !== null) {
            if (!preg_match_all('/\/([^\s\[\]<>()\/%]+)/', $arrayBody, $matches)) {
                return [];
            }

            return array_map(fn (string $name): string => $this->decodePdfName($name), $matches[0]);
        }

        $value = trim($value);
        if (str_starts_with($value, '/')) {
            return [$this->decodePdfName($value)];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function floatValueAfterName(string $body, string $name, array $objects): ?float
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->floatFromPdfValue($objectBody);
        }

        return $this->floatFromPdfValue($value);
    }

    private function floatFromPdfValue(string $value): ?float
    {
        return preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', trim($value), $match) === 1 ? (float) $match[0] : null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->pdfValueToString($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        return $value === 'null' ? null : null;
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);

        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        return $this->valueStartingAtOffset($body, $offset);
    }

    private function valueStartingAtOffset(string $body, int $offset): ?string
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
    }

    /**
     * @param array<int, string> $objects
     */
    private function arrayBodyFromPdfValue(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            return $this->arrayBodyFromValue($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            $objectBody = trim($objects[(int) $match[1]] ?? '');
            return $objectBody === '' ? null : $this->arrayBodyFromPdfValue($objectBody, $objects);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
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
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
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

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
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
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
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

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
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
}
