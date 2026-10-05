<?php

namespace App\Support;

/**
 * Room / item checklist for Move In / Move Out inspections — the same ten
 * rooms and items the old WordPress "UAA Move In/Out" plugin used. Each saved
 * report stores its own copy of the labels, so editing this list never
 * changes old reports.
 */
class MoveInspection
{
    public const VAT_RATE = 0.05;

    public const STATUSES = ['ok' => 'Good', 'maintenance' => 'Maintenance', 'damaged' => 'Damaged'];

    /** @return array<string, array{name: string, items: list<string>}> */
    public static function rooms(): array
    {
        $bed = ['Floor / Wall / Ceiling', 'Door / Window / Wardrobe', 'Switches / Sockets', 'Lights / Others', 'A/C Grill / Thermostat'];

        return [
            'corridor' => ['name' => 'Corridor', 'items' => ['Floor / Wall', 'Ceiling / Door', 'Lights / Switches', 'Sockets / Ringing Bell']],
            'sitting' => ['name' => 'Sitting Area / Living Room', 'items' => ['Floor / Wall', 'Ceiling / Windows', 'Lights / Sockets', 'Switches / Others', 'A/C Grill / Thermostat']],
            'bedroom1' => ['name' => 'Bedroom 1', 'items' => $bed],
            'bedroom2' => ['name' => 'Bedroom 2', 'items' => $bed],
            'bedroom3' => ['name' => 'Bedroom 3', 'items' => $bed],
            'toilet' => ['name' => 'Bathroom / Toilet', 'items' => ['Floor / Wall', 'Ceiling / Window / Door', 'WashBasin / Shower', 'WC / Bidet / Shataf', 'Shower Tray & Accessories', 'Shower Glass Door', 'Water Heater / Exhaust Fan', 'Electric / Lights']],
            'kitchen' => ['name' => 'Kitchen', 'items' => ['Floor / Wall', 'Ceiling / Window', 'Door / Kitchen Cabinet', 'Socket / Switch', 'Lights', 'Fridge / Gas Oven', 'Hood / Dishwasher', 'Washing Machine / Exhaust Fan']],
            'balcony' => ['name' => 'Balcony', 'items' => ['Floor / Wall', 'Railing / Glass', 'Light / Socket']],
            'storeroom' => ['name' => 'Store Room / Laundry', 'items' => ['Floor / Wall', 'Door', 'Light / Socket']],
            'common' => ['name' => 'Common Areas / Others', 'items' => ['Floor / Wall / Ceiling', 'Main Door / Windows', 'Lights / Sockets', 'A/C', 'Others']],
        ];
    }

    /** Fresh checklist rows for one room: everything starts as Good. */
    public static function defaultItems(string $roomId): array
    {
        return array_map(
            fn (string $label) => ['label' => $label, 'status' => 'ok', 'notes' => null, 'price' => null],
            self::rooms()[$roomId]['items'] ?? []
        );
    }

    /** Sum of charges on items that aren't Good, plus 5% VAT. */
    public static function totals(?array $rooms): array
    {
        $sub = 0.0;
        foreach ((array) $rooms as $room) {
            foreach ((array) ($room['items'] ?? []) as $item) {
                if (($item['status'] ?? 'ok') !== 'ok') {
                    $sub += (float) ($item['price'] ?? 0);
                }
            }
        }
        $vat = round($sub * self::VAT_RATE, 2);

        return ['subtotal' => round($sub, 2), 'vat_amount' => $vat, 'total_amount' => round($sub + $vat, 2)];
    }
}
