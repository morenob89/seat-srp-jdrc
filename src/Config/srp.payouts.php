<?php

/**
 * JDRC Ship Replacement Program — flat payout matrix.
 *
 * This is the "defaults in code" table used by the Flat / Matrix SRP mode
 * (see \CryptaTech\Seat\SeatSrp\Helpers\SrpManager::srpGetFlatPrice).
 *
 * Editing corp payouts = editing the numbers below. Values are ISK.
 * A value of 0 means "no default payout — decided manually by an SRP admin"
 * (e.g. Entosis peacetime, Pod/implants, and the Unclassified fallback).
 *
 * Each row has a stable `key` (referenced from the request form and stored on
 * the killmail/quote as `srp_class`), a `meta` tier and a `hull` label used to
 * build the grouped dropdown, and the two payout columns.
 */

return [

    // Operation types the pilot can pick when requesting. The array key is what
    // gets stored as `srp_type`; the string value is the human label. The key
    // must also be a valid column name on every row below.
    'operation_types' => [
        'peacetime' => 'Peacetime',
        'strategic' => 'Strategic / FOB / Corp Op',
    ],

    // Rows of the payout table. peacetime/strategic are ISK (0 = "-"/manual).
    'rows' => [
        ['key' => 't1_frigate',        'meta' => 'T1',            'hull' => 'Frigates',       'peacetime' =>  5_000_000, 'strategic' => 10_000_000],
        ['key' => 't1_destroyer',      'meta' => 'T1',            'hull' => 'Destroyers',     'peacetime' => 10_000_000, 'strategic' => 15_000_000],
        ['key' => 't1_cruiser',        'meta' => 'T1',            'hull' => 'Cruisers',       'peacetime' => 15_000_000, 'strategic' => 20_000_000],
        ['key' => 't1_battlecruiser',  'meta' => 'T1',            'hull' => 'Battlecruisers', 'peacetime' => 25_000_000, 'strategic' => 30_000_000],
        ['key' => 't1_battleship',     'meta' => 'T1',            'hull' => 'Battleships',    'peacetime' => 50_000_000, 'strategic' => 55_000_000],

        ['key' => 'logi_t1_frigate',   'meta' => 'Logistics',     'hull' => 'T1 Frigates',    'peacetime' =>  5_000_000, 'strategic' => 20_000_000],
        ['key' => 'logi_t1_cruiser',   'meta' => 'Logistics',     'hull' => 'T1 Cruisers',    'peacetime' => 15_000_000, 'strategic' => 35_000_000],
        ['key' => 'logi_t2_frigate',   'meta' => 'Logistics',     'hull' => 'T2 Frigates',    'peacetime' => 15_000_000, 'strategic' => 35_000_000],
        ['key' => 'logi_t2_cruiser',   'meta' => 'Logistics',     'hull' => 'T2 Cruisers',    'peacetime' => 30_000_000, 'strategic' => 50_000_000],

        ['key' => 't2f_frigate',       'meta' => 'T2 or Faction', 'hull' => 'Frigates',       'peacetime' => 15_000_000, 'strategic' => 20_000_000],
        ['key' => 't2f_destroyer',     'meta' => 'T2 or Faction', 'hull' => 'Destroyers',     'peacetime' => 20_000_000, 'strategic' => 25_000_000],
        ['key' => 't2f_cruiser',       'meta' => 'T2 or Faction', 'hull' => 'Cruisers',       'peacetime' => 30_000_000, 'strategic' => 35_000_000],
        ['key' => 't2f_battlecruiser', 'meta' => 'T2 or Faction', 'hull' => 'Battlecruisers', 'peacetime' => 40_000_000, 'strategic' => 45_000_000],
        ['key' => 't2f_battleship',    'meta' => 'T2 or Faction', 'hull' => 'Battleships',    'peacetime' => 50_000_000, 'strategic' => 55_000_000],

        ['key' => 't3_destroyer',      'meta' => 'T3',            'hull' => 'Destroyers',     'peacetime' => 20_000_000, 'strategic' => 25_000_000],
        ['key' => 't3_cruiser',        'meta' => 'T3',            'hull' => 'Cruisers',       'peacetime' => 50_000_000, 'strategic' => 55_000_000],

        ['key' => 'entosis_t1',        'meta' => 'Entosis',       'hull' => 'T1 Module',      'peacetime' => 0,          'strategic' => 10_000_000],
        ['key' => 'entosis_t2',        'meta' => 'Entosis',       'hull' => 'T2 Module',      'peacetime' => 0,          'strategic' => 25_000_000],

        // Manual: implant SRP. Too many pod/implant combos to table — the admin
        // sets the payout at approval based on what was actually plugged in.
        ['key' => 'pod',               'meta' => 'Pod',           'hull' => 'Pod / Implants', 'peacetime' => 0,          'strategic' => 0],

        // Fallback: used when the classifier can't match a ship group. Always
        // submittable at 0 ISK; the admin overrides the amount at approval.
        ['key' => 'unclassified',      'meta' => 'Other',         'hull' => 'Unclassified',   'peacetime' => 0,          'strategic' => 0],
    ],
];
