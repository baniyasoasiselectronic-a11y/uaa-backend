<?php

namespace App\Support;

/**
 * Complaint numbers exactly as the old uaa.ae site built them:
 *   building code + Oracle requisition number + complaint-type code   e.g. AN82600AC
 * Buildings and types that are not in these lists get "XX" (same as before).
 */
class ComplaintNumber
{
    public const BUILDINGS = [
        1 => 'AF', 2 => 'BR', 3 => 'FH', 4 => 'KH', 5 => 'MK', 6 => 'MC',
        7 => 'RC', 8 => 'BN', 9 => 'BV', 10 => 'CP', 11 => 'OB', 12 => 'PS',
        17 => 'AV', 18 => 'WB', 19 => 'DG', 20 => 'WA', 21 => 'BS', 22 => 'FV',
        23 => 'KR', 24 => 'SB', 25 => 'GT', 26 => 'AN', 27 => 'AS',
    ];

    public const TYPES = [
        6 => 'CWCP', 9 => 'HA', 11 => 'CWPL', 12 => 'AC', 13 => 'EL',
        14 => 'OT', 15 => 'IT', 16 => 'CWMS', 17 => 'CWGL', 19 => 'CWPN',
    ];

    public static function make(int|string|null $propertyId, int|string|null $typeCode, string $requisition): string
    {
        return (self::BUILDINGS[(int) $propertyId] ?? 'XX').$requisition.(self::TYPES[(int) $typeCode] ?? 'XX');
    }
}
