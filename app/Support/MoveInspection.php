<?php

namespace App\Support;

/**
 * Room / item checklist for Move In / Move Out inspections — the same rooms and
 * items as the old uaa.ae/move-in-out form (Gravity Forms). Each saved report
 * stores its own copy of the labels, so editing this list never changes old reports.
 */
class MoveInspection
{
    public const VAT_RATE = 0.05;

    public const STATUSES = ['ok' => 'Ok', 'maintenance' => 'Maintenance', 'damaged' => 'Damaged'];

    public const TYPES = ['Move In' => 'move_in', 'Move Out' => 'move_out', 'Renewal' => 'renewal', 'Legal' => 'legal'];

    /**
     * Rooms in the order the inspector walks through them. Each room has sections;
     * a section with a null title is the room's general items, the others are
     * sub-headings (Electric, A/C, Sanitary…).
     *
     * @return array<string, array{name: string, sections: list<array{title: ?string, items: list<string>}>}>
     */
    public static function rooms(): array
    {
        return [
            'corridor' => ['name' => 'Corridor', 'sections' => [
                ['title' => null, 'items' => ['Floor / Wall', 'Ceiling / Door']],
                ['title' => 'Electric', 'items' => ['Lights / Switches', 'Sockets / Ringing Bell']],
            ]],
            'sitting' => ['name' => 'Sitting Area', 'sections' => [
                ['title' => null, 'items' => ['Floor / Wall', 'Ceiling / Windows']],
                ['title' => 'Electric', 'items' => ['Lights / Sockets', 'Switches / Others']],
            ]],
            'bedroom' => ['name' => 'Bedroom', 'sections' => [
                ['title' => null, 'items' => ['Floor / Wall / Ceiling', 'Door / Window / Wardrobe']],
                ['title' => 'Electric', 'items' => ['Switches / Sockets', 'Lights / Others']],
                ['title' => 'A/C', 'items' => ['Grill / Thermostat', 'Others']],
            ]],
            'toilet' => ['name' => 'Toilet', 'sections' => [
                ['title' => null, 'items' => ['Floor / Wall', 'Ceiling / Window / Door']],
                ['title' => 'Mixture', 'items' => ['Wash Basin / Shower', 'Shataf / P-day']],
                ['title' => 'Sanitary', 'items' => ['Wash Basin / Counter', 'W/C & Shower Tray', 'Shower Glass Door', 'Accessory', 'Mirror', 'Water Heater / Exhaust Fan']],
                ['title' => 'Electric', 'items' => ['Switch / Sockets', 'Lights / Mirror Lights', 'Others']],
            ]],
            'kitchen' => ['name' => 'Kitchen', 'sections' => [
                ['title' => null, 'items' => ['Floor / Wall', 'Ceiling / Window', 'Door / Kitchen Cabinet']],
                ['title' => 'Electric', 'items' => ['Socket / Switch', 'Light']],
                ['title' => 'Kitchen Appliances', 'items' => ['Fridge / Gas Oven', 'Hood / Dish Washers', 'Washing Machine / Exhaust Fan']],
            ]],
        ];
    }

    /** Sum of every charge entered (like the old form's calculated total), plus 5% VAT. */
    public static function totals(?array $rooms): array
    {
        $sub = 0.0;
        foreach ((array) $rooms as $room) {
            foreach ((array) ($room['items'] ?? []) as $item) {
                $sub += (float) ($item['price'] ?? 0);
            }
        }
        $vat = round($sub * self::VAT_RATE, 2);

        return ['subtotal' => round($sub, 2), 'vat_amount' => $vat, 'total_amount' => round($sub + $vat, 2)];
    }
}
