<?php

namespace CryptaTech\Seat\SeatSrp\Helpers;

use CryptaTech\Seat\SeatSrp\Models\Sde\InvType;
use Illuminate\Support\Facades\DB;

/**
 * Maps an EVE ship type into a row `key` of the flat payout matrix
 * (config/srp.payouts.php), used by the Flat / Matrix SRP mode.
 *
 * EVE puts T2/T3 hulls in their own inventory groups rather than tagging the
 * base hull, so classification is driven by a curated groupID map. For the base
 * hull groups (Frigate/Destroyer/Cruiser/Battlecruiser/Battleship) the tier
 * (T1 vs Faction) is resolved from invMetaTypes.metaGroupID.
 *
 * This is a best-effort auto-guess. It can never reliably detect T1-logistics
 * (same group as combat T1 hulls) or Entosis (a fitted module, not a hull);
 * those are corrected by the pilot via the request-form dropdown. Anything the
 * map does not cover resolves to the `unclassified` fallback (0 ISK, admin sets
 * the amount), so classify() always returns a valid, submittable row key.
 */
class ShipClassifier
{
    /**
     * Fallback row key when nothing matches. Must exist in config('srp.payouts').
     */
    public const FALLBACK = 'unclassified';

    /**
     * metaGroupID for Faction ships (SDE invMetaTypes / metaGroups).
     */
    private const META_FACTION = 4;

    /**
     * Base hull groups whose tier is resolved from metaGroupID.
     * groupID => size token used to build the row key (t1_<size> / t2f_<size>).
     */
    private static array $SIZE_GROUPS = [
        25  => 'frigate',       // Frigate
        420 => 'destroyer',     // Destroyer
        26  => 'cruiser',       // Cruiser
        419 => 'battlecruiser', // Battlecruiser
        27  => 'battleship',    // Battleship
    ];

    /**
     * Groups that map straight to a fixed matrix row regardless of metaGroup.
     */
    private static array $FIXED_GROUPS = [
        // T2 frigates
        324  => 't2f_frigate',       // Assault Frigate
        831  => 't2f_frigate',       // Interceptor
        830  => 't2f_frigate',       // Covert Ops
        893  => 't2f_frigate',       // Electronic Attack Ship
        834  => 't2f_frigate',       // Stealth Bomber
        // T2 destroyers
        541  => 't2f_destroyer',     // Interdictor
        1534 => 't2f_destroyer',     // Command Destroyer
        // T2 cruisers
        358  => 't2f_cruiser',       // Heavy Assault Cruiser
        894  => 't2f_cruiser',       // Heavy Interdiction Cruiser
        833  => 't2f_cruiser',       // Force Recon Ship
        906  => 't2f_cruiser',       // Combat Recon Ship
        // T2 battlecruisers
        540  => 't2f_battlecruiser', // Command Ship
        // T2 battleships
        900  => 't2f_battleship',    // Marauder
        898  => 't2f_battleship',    // Black Ops
        // Logistics (T2)
        832  => 'logi_t2_cruiser',   // Logistics
        1527 => 'logi_t2_frigate',   // Logistics Frigate
        // Tech III
        963  => 't3_cruiser',        // Strategic Cruiser
        1305 => 't3_destroyer',      // Tactical Destroyer
        // Pod / capsule (payout decided manually by the admin)
        29   => 'pod',               // Capsule
    ];

    /**
     * Resolve the best-guess matrix row key for a ship type.
     *
     * @param  int|null  $typeId  the ship (hull) type id
     * @return string a row key that is guaranteed to exist in the matrix
     */
    public static function classify(?int $typeId): string
    {
        if (is_null($typeId))
            return self::FALLBACK;

        $type = InvType::find($typeId);
        if (is_null($type))
            return self::FALLBACK;

        $groupId = (int) $type->groupID;

        if (array_key_exists($groupId, self::$FIXED_GROUPS))
            return self::$FIXED_GROUPS[$groupId];

        if (array_key_exists($groupId, self::$SIZE_GROUPS)) {
            $size = self::$SIZE_GROUPS[$groupId];
            $tier = self::metaGroup($typeId) === self::META_FACTION ? 't2f' : 't1';

            return $tier . '_' . $size;
        }

        return self::FALLBACK;
    }

    /**
     * Look up the SDE meta group for a type (null when the type isn't listed,
     * which is the case for most Tech I items).
     */
    private static function metaGroup(int $typeId): ?int
    {
        $row = DB::table('invMetaTypes')->where('typeID', $typeId)->first();

        return $row ? (int) $row->metaGroupID : null;
    }
}
