<?php

/**
 * A small QR code encoder, used to print scannable labels for locations and
 * items. Byte mode only, versions 1-10, which is ample for a URL on a local
 * network (up to 213 characters at error correction level M).
 *
 * Everything here follows ISO/IEC 18004. The tables below are from that spec.
 */

/** Total data codewords per version and error correction level. */
const QR_BLOCKS = [
    //        [ec codewords per block, [[block count, data codewords], ...]]
    1  => ['L' => [7,  [[1, 19]]],  'M' => [10, [[1, 16]]],  'Q' => [13, [[1, 13]]],           'H' => [17, [[1, 9]]]],
    2  => ['L' => [10, [[1, 34]]],  'M' => [16, [[1, 28]]],  'Q' => [22, [[1, 22]]],           'H' => [28, [[1, 16]]]],
    3  => ['L' => [15, [[1, 55]]],  'M' => [26, [[1, 44]]],  'Q' => [18, [[2, 17]]],           'H' => [22, [[2, 13]]]],
    4  => ['L' => [20, [[1, 80]]],  'M' => [18, [[2, 32]]],  'Q' => [26, [[2, 24]]],           'H' => [16, [[4, 9]]]],
    5  => ['L' => [26, [[1, 108]]], 'M' => [24, [[2, 43]]],  'Q' => [18, [[2, 15], [2, 16]]],  'H' => [22, [[2, 11], [2, 12]]]],
    6  => ['L' => [18, [[2, 68]]],  'M' => [16, [[4, 27]]],  'Q' => [24, [[4, 19]]],           'H' => [28, [[4, 15]]]],
    7  => ['L' => [20, [[2, 78]]],  'M' => [18, [[4, 31]]],  'Q' => [18, [[2, 14], [4, 15]]],  'H' => [26, [[4, 13], [1, 14]]]],
    8  => ['L' => [24, [[2, 97]]],  'M' => [22, [[2, 38], [2, 39]]], 'Q' => [22, [[4, 18], [2, 19]]], 'H' => [26, [[4, 14], [2, 15]]]],
    9  => ['L' => [30, [[2, 116]]], 'M' => [22, [[3, 36], [2, 37]]], 'Q' => [20, [[4, 16], [4, 17]]], 'H' => [24, [[4, 12], [4, 13]]]],
    10 => ['L' => [18, [[2, 68], [2, 69]]], 'M' => [26, [[4, 43], [1, 44]]], 'Q' => [24, [[6, 19], [2, 20]]], 'H' => [28, [[6, 15], [2, 16]]]],
];

/** Alignment pattern centres per version. */
const QR_ALIGNMENT = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
];

/** 18 bit version information, versions 7 and up. */
const QR_VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];

/** Error correction level indicator bits. */
const QR_EC_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

/** Exponent and logarithm tables for GF(256) with primitive polynomial 0x11D. */
function qrGaloisTables(): array
{
    static $tables = null;

    if ($tables === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;

        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;

            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        $tables = [$exp, $log];
    }

    return $tables;
}

/** Generator polynomial for $degree error correction codewords. */
function qrGeneratorPoly(int $degree): array
{
    [$exp, $log] = qrGaloisTables();
    $poly = [1];

    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);

        foreach ($poly as $j => $coefficient) {
            $next[$j] ^= $coefficient;
            $next[$j + 1] ^= $coefficient ? $exp[($log[$coefficient] + $i) % 255] : 0;
        }

        $poly = $next;
    }

    return $poly;
}

/** Reed-Solomon error correction codewords for one block. */
function qrErrorCorrection(array $data, int $degree): array
{
    [$exp, $log] = qrGaloisTables();

    $generator = qrGeneratorPoly($degree);
    $remainder = array_merge($data, array_fill(0, $degree, 0));

    foreach ($data as $i => $ignored) {
        $lead = $remainder[$i];

        if ($lead === 0) {
            continue;
        }

        foreach ($generator as $j => $coefficient) {
            $remainder[$i + $j] ^= $exp[($log[$coefficient] + $log[$lead]) % 255];
        }
    }

    return array_slice($remainder, count($data), $degree);
}

/** Data codeword capacity of a version at an error correction level. */
function qrDataCapacity(int $version, string $ecLevel): int
{
    $total = 0;

    foreach (QR_BLOCKS[$version][$ecLevel][1] as [$count, $dataCodewords]) {
        $total += $count * $dataCodewords;
    }

    return $total;
}

/** Character count indicator width for byte mode. */
function qrLengthBits(int $version): int
{
    return ($version < 10) ? 8 : 16;
}

/** Smallest version that fits $length bytes, or 0 when it will not fit. */
function qrChooseVersion(int $length, string $ecLevel): int
{
    foreach (array_keys(QR_BLOCKS) as $version) {
        $needed = (int)ceil((4 + qrLengthBits($version) + ($length * 8)) / 8);

        if ($needed <= qrDataCapacity($version, $ecLevel)) {
            return $version;
        }
    }

    return 0;
}

/** The full interleaved codeword stream for $text. */
function qrCodewords(string $text, int $version, string $ecLevel): array
{
    [$ecPerBlock, $blockSpec] = QR_BLOCKS[$version][$ecLevel];

    $bits = '0100' . str_pad(decbin(strlen($text)), qrLengthBits($version), '0', STR_PAD_LEFT);

    foreach (str_split($text) as $character) {
        $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
    }

    $capacityBits = qrDataCapacity($version, $ecLevel) * 8;

    $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
    $bits .= str_repeat('0', (8 - (strlen($bits) % 8)) % 8);

    $codewords = array_map('bindec', str_split($bits, 8));
    $padding = [0xEC, 0x11];

    for ($i = 0; count($codewords) < $capacityBits / 8; $i++) {
        $codewords[] = $padding[$i % 2];
    }

    // Split into blocks, then interleave the data and the error correction.
    $dataBlocks = [];
    $ecBlocks = [];
    $offset = 0;

    foreach ($blockSpec as [$count, $dataCodewords]) {
        for ($i = 0; $i < $count; $i++) {
            $block = array_slice($codewords, $offset, $dataCodewords);
            $offset += $dataCodewords;

            $dataBlocks[] = $block;
            $ecBlocks[] = qrErrorCorrection($block, $ecPerBlock);
        }
    }

    $stream = [];

    foreach ([$dataBlocks, $ecBlocks] as $blocks) {
        for ($i = 0; $i < max(array_map('count', $blocks)); $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $stream[] = $block[$i];
                }
            }
        }
    }

    return $stream;
}

/** Finder patterns, timing patterns, alignment patterns and reserved areas. */
function qrFunctionPatterns(int $version, int $size): array
{
    $matrix = array_fill(0, $size, array_fill(0, $size, 0));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $place = function (int $top, int $left, array $pattern) use (&$matrix, &$reserved, $size) {
        foreach ($pattern as $r => $rowBits) {
            foreach ($rowBits as $c => $bit) {
                $row = $top + $r;
                $col = $left + $c;

                if ($row >= 0 && $row < $size && $col >= 0 && $col < $size) {
                    $matrix[$row][$col] = $bit;
                    $reserved[$row][$col] = true;
                }
            }
        }
    };

    // Finder patterns with their separators, as an 8x8 block.
    $finder = [];

    for ($r = -1; $r <= 7; $r++) {
        $rowBits = [];

        for ($c = -1; $c <= 7; $c++) {
            $inRing = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
            $inCore = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;

            $rowBits[] = ($inRing || $inCore) ? 1 : 0;
        }

        $finder[] = $rowBits;
    }

    $place(-1, -1, $finder);
    $place(-1, $size - 8, $finder);
    $place($size - 8, -1, $finder);

    // Timing patterns.
    for ($i = 8; $i < $size - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;

        $matrix[6][$i] = $bit;
        $reserved[6][$i] = true;
        $matrix[$i][6] = $bit;
        $reserved[$i][6] = true;
    }

    // Alignment patterns, skipping the three that would sit on a finder.
    $centres = QR_ALIGNMENT[$version];

    foreach ($centres as $row) {
        foreach ($centres as $col) {
            $onFinder = ($row === 6 && $col === 6)
                || ($row === 6 && $col === $size - 7)
                || ($row === $size - 7 && $col === 6);

            if ($onFinder) {
                continue;
            }

            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    $matrix[$row + $r][$col + $c] = (max(abs($r), abs($c)) !== 1) ? 1 : 0;
                    $reserved[$row + $r][$col + $c] = true;
                }
            }
        }
    }

    // Dark module.
    $matrix[$size - 8][8] = 1;
    $reserved[$size - 8][8] = true;

    // Format information areas.
    for ($i = 0; $i <= 8; $i++) {
        $reserved[8][$i] = true;
        $reserved[$i][8] = true;
    }

    for ($i = 0; $i < 8; $i++) {
        $reserved[8][$size - 1 - $i] = true;
        $reserved[$size - 1 - $i][8] = true;
    }

    // Version information areas.
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $reserved[intdiv($i, 3)][$size - 11 + ($i % 3)] = true;
            $reserved[$size - 11 + ($i % 3)][intdiv($i, 3)] = true;
        }
    }

    return [$matrix, $reserved];
}

/** True when the module at ($row, $col) is inverted by $mask. */
function qrMaskApplies(int $mask, int $row, int $col): bool
{
    switch ($mask) {
        case 0: return ($row + $col) % 2 === 0;
        case 1: return $row % 2 === 0;
        case 2: return $col % 3 === 0;
        case 3: return ($row + $col) % 3 === 0;
        case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0;
        case 5: return (($row * $col) % 2) + (($row * $col) % 3) === 0;
        case 6: return ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0;
        default: return ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0;
    }
}

/**
 * Penalty rule 3: each 1:1:3:1:1 dark/light run bordered by four light
 * modules scores 40. The light run may be cut short by the edge of the
 * symbol, and a pattern hard against either edge always counts.
 */
function qrPatternPenalty(string $line, int $size): int
{
    $penalty = 0;
    $index = strpos($line, '1011101');

    while ($index !== false) {
        $after = $index + 7;
        $start = max($index - 4, 0);

        $before = substr($line, $start, $index - $start);
        $following = substr($line, $after, 4);

        if ($index === 0 || $index === $size - 7
            || strpos($before, '1') === false
            || strpos($following, '1') === false
        ) {
            $penalty += 40;
            $next = $after;
        } else {
            // Overlapping run: the next match can only start four modules on.
            $next = $index + 4;
        }

        $index = strpos($line, '1011101', $next);
    }

    return $penalty;
}

/** The four penalty rules used to pick the least noisy mask. */
function qrPenalty(array $matrix, int $size): int
{
    return array_sum(qrPenaltyParts($matrix, $size));
}

/** The individual rule 1 to 4 scores, in order. */
function qrPenaltyParts(array $matrix, int $size): array
{
    $adjacent = 0;
    $blocks = 0;
    $patterns = 0;
    $dark = 0;

    // Rules 1 and 3, applied across rows then columns.
    for ($pass = 0; $pass < 2; $pass++) {
        for ($i = 0; $i < $size; $i++) {
            $line = [];

            for ($j = 0; $j < $size; $j++) {
                $line[] = $pass === 0 ? $matrix[$i][$j] : $matrix[$j][$i];
            }

            $runValue = $line[0];
            $runLength = 1;

            for ($j = 1; $j < $size; $j++) {
                if ($line[$j] === $runValue) {
                    $runLength++;
                    continue;
                }

                if ($runLength >= 5) {
                    $adjacent += 3 + ($runLength - 5);
                }

                $runValue = $line[$j];
                $runLength = 1;
            }

            if ($runLength >= 5) {
                $adjacent += 3 + ($runLength - 5);
            }

            $patterns += qrPatternPenalty(implode('', $line), $size);
        }
    }

    // Rule 2, plus the dark module tally for rule 4.
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            $dark += $matrix[$row][$col];

            if ($row + 1 < $size && $col + 1 < $size
                && $matrix[$row][$col] === $matrix[$row][$col + 1]
                && $matrix[$row][$col] === $matrix[$row + 1][$col]
                && $matrix[$row][$col] === $matrix[$row + 1][$col + 1]
            ) {
                $blocks += 3;
            }
        }
    }

    $percent = ($dark * 100) / ($size * $size);
    $proportion = 10 * (int)floor(abs($percent - 50) / 5);

    return [$adjacent, $blocks, $patterns, $proportion];
}

/** 15 bit format information for an error correction level and mask. */
function qrFormatBits(string $ecLevel, int $mask): int
{
    $data = (QR_EC_BITS[$ecLevel] << 3) | $mask;
    $remainder = $data << 10;

    for ($i = 14; $i >= 10; $i--) {
        if (($remainder >> $i) & 1) {
            $remainder ^= 0x537 << ($i - 10);
        }
    }

    return ((($data << 10) | $remainder) ^ 0x5412) & 0x7FFF;
}

/**
 * The QR code for $text as a grid of 0/1 rows.
 *
 * Returns an empty array when the text is too long for version 10.
 */
function qrMatrix(string $text, string $ecLevel = 'M', ?int $forceMask = null): array
{
    $version = qrChooseVersion(strlen($text), $ecLevel);

    if ($version === 0) {
        return [];
    }

    $size = ($version * 4) + 17;

    [$base, $reserved] = qrFunctionPatterns($version, $size);

    // Version information, written once and unaffected by masking.
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $bit = (QR_VERSION_INFO[$version] >> $i) & 1;

            $base[intdiv($i, 3)][$size - 11 + ($i % 3)] = $bit;
            $base[$size - 11 + ($i % 3)][intdiv($i, 3)] = $bit;
        }
    }

    // Lay the codewords out in the upward/downward zigzag.
    $bits = [];

    foreach (qrCodewords($text, $version, $ecLevel) as $codeword) {
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($codeword >> $i) & 1;
        }
    }

    $matrix = $base;
    $bitIndex = 0;
    $row = $size - 1;
    $upward = true;

    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) {
            $col--;
        }

        while (true) {
            for ($offset = 0; $offset < 2; $offset++) {
                if (!$reserved[$row][$col - $offset]) {
                    $matrix[$row][$col - $offset] = $bits[$bitIndex] ?? 0;
                    $bitIndex++;
                }
            }

            $row += $upward ? -1 : 1;

            if ($row < 0 || $row >= $size) {
                $row -= $upward ? -1 : 1;
                $upward = !$upward;
                break;
            }
        }
    }

    // Try every mask and keep the one the spec scores lowest. The format
    // information is deliberately written afterwards: ISO/IEC 18004 section 7.8
    // scores the masked encoding region on its own.
    $best = null;
    $bestMask = 0;
    $bestPenalty = null;

    for ($mask = 0; $mask < 8; $mask++) {
        if ($forceMask !== null && $mask !== $forceMask) {
            continue;
        }

        $candidate = $matrix;

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$reserved[$r][$c] && qrMaskApplies($mask, $r, $c)) {
                    $candidate[$r][$c] ^= 1;
                }
            }
        }

        $penalty = qrPenalty($candidate, $size);

        if ($bestPenalty === null || $penalty < $bestPenalty) {
            $bestPenalty = $penalty;
            $bestMask = $mask;
            $best = $candidate;
        }
    }

    qrWriteFormat($best, $size, $ecLevel, $bestMask);

    return $best;
}

/** Write both copies of the format information into a masked matrix. */
function qrWriteFormat(array &$matrix, int $size, string $ecLevel, int $mask): void
{
    $format = qrFormatBits($ecLevel, $mask);

    for ($i = 0; $i < 15; $i++) {
        $bit = ($format >> $i) & 1;

        // Copy around the top left finder: up column 8, then along row 8.
        if ($i < 6) {
            $matrix[$i][8] = $bit;
        } elseif ($i === 6) {
            $matrix[7][8] = $bit;
        } elseif ($i === 7) {
            $matrix[8][8] = $bit;
        } elseif ($i === 8) {
            $matrix[8][7] = $bit;
        } else {
            $matrix[8][14 - $i] = $bit;
        }

        // Copy split between the other two finders: along row 8, then up column 8.
        if ($i < 8) {
            $matrix[8][$size - 1 - $i] = $bit;
        } else {
            $matrix[$size - 15 + $i][8] = $bit;
        }
    }
}

/** The QR code for $text as a self contained SVG, sized in pixels. */
function qrSvg(string $text, int $pixels = 120, string $ecLevel = 'M'): string
{
    $matrix = qrMatrix($text, $ecLevel);

    if (!$matrix) {
        return '';
    }

    $size = count($matrix);
    $quiet = 4;
    $extent = $size + ($quiet * 2);
    $path = '';

    foreach ($matrix as $row => $cells) {
        foreach ($cells as $col => $bit) {
            if ($bit) {
                $path .= 'M' . ($col + $quiet) . ' ' . ($row + $quiet) . 'h1v1h-1z';
            }
        }
    }

    return trim(templateHtml('qr-svg', compact('pixels', 'extent', 'path')));
}
