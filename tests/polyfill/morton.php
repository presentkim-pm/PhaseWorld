<?php

/**
 * Test-only stand-in for ext-morton's morton2d_encode(), used by Item::getStateId() (and so Item::equals()).
 * Interleaves the low 32 bits of both coordinates, which is what the extension does.
 */

declare(strict_types=1);

function morton2d_encode(int $x, int $y) : int{
    $result = 0;
    for($i = 0; $i < 32; ++$i){
        $result |= (($x >> $i) & 1) << (2 * $i);
        $result |= (($y >> $i) & 1) << (2 * $i + 1);
    }
    return $result;
}

/**
 * Test-only stand-in for ext-morton's morton3d_encode(), used by World::blockHash().
 * Interleaves the low 21 bits of the three coordinates (x in bit 0, y in bit 1, z in bit 2 of each triple).
 */
function morton3d_encode(int $x, int $y, int $z) : int{
    $result = 0;
    for($i = 0; $i < 21; ++$i){
        $result |= (($x >> $i) & 1) << (3 * $i);
        $result |= (($y >> $i) & 1) << (3 * $i + 1);
        $result |= (($z >> $i) & 1) << (3 * $i + 2);
    }
    return $result;
}

/**
 * Test-only stand-in for ext-morton's morton3d_decode(), the inverse of morton3d_encode().
 *
 * @return int[] [x, y, z]
 */
function morton3d_decode(int $code) : array{
    $x = $y = $z = 0;
    for($i = 0; $i < 21; ++$i){
        $x |= (($code >> (3 * $i)) & 1) << $i;
        $y |= (($code >> (3 * $i + 1)) & 1) << $i;
        $z |= (($code >> (3 * $i + 2)) & 1) << $i;
    }
    return [$x, $y, $z];
}
