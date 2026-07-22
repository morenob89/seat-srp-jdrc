<?php

namespace CryptaTech\Seat\SeatSrp\Helpers;

/**
 * Read/write helper for the Flat / Matrix payout table.
 *
 * The table defaults live in config('srp.payouts') (defaults-in-code). Admin
 * edits made from the "SRP Payouts" page are stored as a JSON override map in
 * the SeAT settings table and merged over the config defaults at read time.
 *
 * Override map shape (only differs-from-default cells are stored, so clearing
 * an override reverts that cell to the config default):
 *   { "<rowKey>": { "<opType>": <isk int>, ... }, ... }
 */
class SrpPayouts
{
    /** Settings key holding the JSON override map. */
    public const SETTING_KEY = 'cryptatech_seat_srp_payout_overrides';

    /**
     * Operation-type columns (key => human label), straight from config.
     */
    public static function operationTypes(): array
    {
        return config('srp.payouts.operation_types', []);
    }

    /**
     * The decoded override map, keyed by row key then op-type.
     */
    public static function overrides(): array
    {
        $raw = setting(self::SETTING_KEY, true);

        if (empty($raw)) {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The payout rows with any stored overrides applied. Each returned row keeps
     * its config structure (key/meta/hull + one column per operation type) and
     * gains an `overridden` map flagging which op-type cells came from an admin
     * edit rather than the config default.
     */
    public static function rows(): array
    {
        $overrides = self::overrides();
        $opTypes = array_keys(self::operationTypes());

        return array_map(function (array $row) use ($overrides, $opTypes) {
            $rowOverrides = $overrides[$row['key']] ?? [];
            $row['overridden'] = [];

            foreach ($opTypes as $opType) {
                if (array_key_exists($opType, $rowOverrides) && is_numeric($rowOverrides[$opType])) {
                    $row[$opType] = (int) $rowOverrides[$opType];
                    $row['overridden'][$opType] = true;
                }
            }

            return $row;
        }, config('srp.payouts.rows', []));
    }

    /**
     * Persist admin edits for a single row. $values is an [opType => isk] map;
     * a value equal to the config default is dropped from the override map so
     * the cell tracks the default again.
     *
     * @return bool  true if the row key is valid and the save happened
     */
    public static function saveRow(string $rowKey, array $values): bool
    {
        $defaults = collect(config('srp.payouts.rows', []))->firstWhere('key', $rowKey);

        if (is_null($defaults)) {
            return false;
        }

        $opTypes = array_keys(self::operationTypes());
        $overrides = self::overrides();
        $rowOverrides = $overrides[$rowKey] ?? [];

        foreach ($opTypes as $opType) {
            if (! array_key_exists($opType, $values) || $values[$opType] === null || $values[$opType] === '') {
                continue;
            }

            $value = (int) $values[$opType];
            $default = (int) ($defaults[$opType] ?? 0);

            if ($value === $default) {
                unset($rowOverrides[$opType]);
            } else {
                $rowOverrides[$opType] = $value;
            }
        }

        if (empty($rowOverrides)) {
            unset($overrides[$rowKey]);
        } else {
            $overrides[$rowKey] = $rowOverrides;
        }

        setting([self::SETTING_KEY, json_encode($overrides)], true);

        return true;
    }
}
