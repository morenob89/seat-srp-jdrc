<?php

namespace CryptaTech\Seat\SeatSrp\Helpers;

use CryptaTech\Seat\SeatSrp\Enum\SRPCategoryEnum;
use CryptaTech\Seat\SeatSrp\Helpers\ShipClassifier;
use CryptaTech\Seat\SeatSrp\Items\PriceableSRPItem;
use CryptaTech\Seat\SeatSrp\Models\AdvRule;
use CryptaTech\Seat\SeatSrp\Models\Eve\Insurance;
use Illuminate\Support\Collection;
use RecursiveTree\Seat\PricesCore\Exceptions\PriceProviderException;
use RecursiveTree\Seat\PricesCore\Facades\PriceProviderSystem;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Killmails\Killmail;

trait SrpManager
{

    public static $FIT_FLAGS = [
        11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 87,
        92, 93, 94, 95, 96, 97, 98, 99, 125, 126, 127, 128, 129, 130, 131, 132, 158, 159, 160, 161, 162, 163,
    ];
    public static $CARGO_FLAGS = [5, 90, 133, 134, 135, 136, 137, 138, 139, 140, 141, 142, 143, 148, 149, 151, 155, 176, 177, 179];

    /**
     * Static map of EVE inventory flag IDs to their flag names.
     *
     * This replaces the previous lookup against the legacy `invFlags` SDE table,
     * which is no longer seeded by modern SeAT (eveapi 5.x/6.x ship no invFlags
     * seeder or migration). When that table is empty, InvFlag::find() returned
     * null and reading ->flagName threw a fatal error on every killmail with
     * fitted items, surfacing to the user as "Invalid killmail address".
     *
     * Flag IDs are static in EVE, so a hard-coded map is safe. Only the flags
     * whose names drive the slot display switch are listed here; any other flag
     * (e.g. specialised holds) resolves to null and is still priced, just not
     * placed into a named slot — matching the previous behaviour.
     */
    public static $FLAG_NAMES = [
        5 => 'Cargo',
        11 => 'LoSlot0', 12 => 'LoSlot1', 13 => 'LoSlot2', 14 => 'LoSlot3',
        15 => 'LoSlot4', 16 => 'LoSlot5', 17 => 'LoSlot6', 18 => 'LoSlot7',
        19 => 'MedSlot0', 20 => 'MedSlot1', 21 => 'MedSlot2', 22 => 'MedSlot3',
        23 => 'MedSlot4', 24 => 'MedSlot5', 25 => 'MedSlot6', 26 => 'MedSlot7',
        27 => 'HiSlot0', 28 => 'HiSlot1', 29 => 'HiSlot2', 30 => 'HiSlot3',
        31 => 'HiSlot4', 32 => 'HiSlot5', 33 => 'HiSlot6', 34 => 'HiSlot7',
        87 => 'DroneBay',
        92 => 'RigSlot0', 93 => 'RigSlot1', 94 => 'RigSlot2', 95 => 'RigSlot3',
        96 => 'RigSlot4', 97 => 'RigSlot5', 98 => 'RigSlot6', 99 => 'RigSlot7',
        125 => 'SubSystemSlot0', 126 => 'SubSystemSlot1', 127 => 'SubSystemSlot2', 128 => 'SubSystemSlot3',
        129 => 'SubSystemSlot4', 130 => 'SubSystemSlot5', 131 => 'SubSystemSlot6', 132 => 'SubSystemSlot7',
        158 => 'FighterBay',
        159 => 'FighterTube0', 160 => 'FighterTube1', 161 => 'FighterTube2', 162 => 'FighterTube3', 163 => 'FighterTube4',
        // Flags that were historically missing from the SDE and previously patched
        // in by the `srp:glue:flag` (FlagShim) command; included here so the map is
        // fully self-contained and that command is no longer required.
        179 => 'FrigateBay', 180 => 'CoreRoom',
    ];

    private function srpPopulateSlots(Killmail $killMail, string $opType = 'peacetime', ?string $rowKey = null): array
    {
        // Guard against a killmail whose victim/detail data failed to load from
        // ESI. Without this, dereferencing $killMail->victim below would throw a
        // cryptic "property on null" error.
        if (is_null($killMail->victim)) {
            throw new \RuntimeException('Killmail details are not available yet (victim data missing). The killmail may still be loading from ESI — please try again shortly.');
        }

        $priceList = [];
        $slots = [
            'killId' => 0,
            'price' => 0.0,
            'shipType' => null,
            'characterName' => null,
            'cargo' => [],
            'dronebay' => [],
        ];
        // dd($killMail->victim->items);
        foreach ($killMail->victim->items as $item) {
            $searchedItem = $item;
            // Resolve the slot name from a static map instead of the legacy
            // `invFlags` table. Unknown flags resolve to null (still priced below).
            $flagName = self::$FLAG_NAMES[$item->pivot->flag] ?? null;
            if (! is_object($searchedItem)) {
            } else {
                $priceitem = array_key_exists($searchedItem->typeID, $priceList) ? $priceList[$searchedItem->typeID] : new PriceableSRPItem($searchedItem, $item->pivot->flag, 0);

                switch ($flagName) {
                    case null:
                        // Unknown/unmapped flag: price it, but don't place it in a named slot.
                        break;
                    case 'Cargo':
                        $slots['cargo'][$searchedItem->typeID]['name'] = $searchedItem->typeName;
                        if (! isset($slots['cargo'][$searchedItem->typeID]['qty']))
                            $slots['cargo'][$searchedItem->typeID]['qty'] = 0;
                        if (! is_null($item->pivot->quantity_destroyed))
                            $slots['cargo'][$searchedItem->typeID]['qty'] += $item->pivot->quantity_destroyed;
                        if (! is_null($item->pivot->quantity_dropped))
                            $slots['cargo'][$searchedItem->typeID]['qty'] += $item->pivot->quantity_dropped;
                        break;
                    case 'DroneBay':
                        $slots['dronebay'][$searchedItem->typeID]['name'] = $searchedItem->typeName;
                        if (! isset($slots['dronebay'][$searchedItem->typeID]['qty']))
                            $slots['dronebay'][$searchedItem->typeID]['qty'] = 0;
                        if (! is_null($item->pivot->quantity_destroyed))
                            $slots['dronebay'][$searchedItem->typeID]['qty'] += $item->pivot->quantity_destroyed;
                        if (! is_null($item->pivot->quantity_dropped))
                            $slots['dronebay'][$searchedItem->typeID]['qty'] += $item->pivot->quantity_dropped;
                        break;
                    default:
                        if (! preg_match('/(Charge|Script|[SML])$/', $searchedItem->typeName)) {
                            $slots[$flagName]['id'] = $searchedItem->typeID;
                            $slots[$flagName]['name'] = $searchedItem->typeName;
                            if (! isset($slots[$flagName]['qty']))
                                $slots[$flagName]['qty'] = 0;
                            if (! is_null($item->pivot->quantity_destroyed))
                                $slots[$flagName]['qty'] += $item->pivot->quantity_destroyed;
                            if (! is_null($item->pivot->quantity_dropped))
                                $slots[$flagName]['qty'] += $item->pivot->quantity_dropped;
                        }
                        break;
                }
                // Yes all of this should be neater... Deal with it for now.
                if (! is_null($item->pivot->quantity_destroyed))
                    $priceitem->incrementAmount($item->pivot->quantity_destroyed);
                if (! is_null($item->pivot->quantity_dropped))
                    $priceitem->incrementAmount($item->pivot->quantity_dropped);
                $priceList[$searchedItem->typeID] = $priceitem;
                // array_push($priceList, $priceitem);
            }
        }

        $searchedItem = $killMail->victim->ship;
        $slots['typeId'] = $killMail->victim->ship->typeID;
        $slots['shipType'] = $searchedItem->typeName;
        array_push($priceList, new PriceableSRPItem($searchedItem, 0, 1));

        // dd($priceList, $slots);

        $priceList = collect($priceList);

        $prices = $this->srpGetPrice($killMail, $priceList, $opType, $rowKey);

        $pilot = CharacterInfo::find($killMail->victim->character_id);

        $slots['characterName'] = $killMail->victim->character_id;
        if (! is_null($pilot))
            $slots['characterName'] = $pilot->name;

        $slots['killId'] = $killMail->killmail_id;
        $slots['price'] = $prices;

        return $slots;
    }

    private function srpGetPrice(Killmail $killmail, Collection $priceList, string $opType = 'peacetime', ?string $rowKey = null): array
    {
        // Switching logic between the pricing modes.
        //   '2' = flat / matrix (JDRC fixed payout table)
        //   '1' = advanced (percentage-of-value rules)
        //   else = simple (whole killmail value)
        // Advanced is tried before simple because if the setting has never been
        // set it will be empty and simple is the safe default.
        $mode = setting('cryptatech_seat_srp_advanced_srp', true);

        if ($mode == '2') {
            return $this->srpGetFlatPrice($killmail, $rowKey, $opType);
        }

        if ($mode == '1') {
            return $this->srpGetAdvancedPrice($killmail, $priceList);
        }

        return $this->srpGetSimplePrice($killmail, $priceList);
    }

    /**
     * Flat / Matrix pricing: pay a fixed ISK amount from config('srp.payouts')
     * based on the ship-class row and the chosen operation-type column. Ignores
     * item prices entirely — no price provider is consulted.
     *
     * @param  string|null  $rowKey  the pilot's chosen matrix row, or null to auto-classify
     * @param  string  $opType  the operation-type column key (peacetime|strategic)
     */
    private function srpGetFlatPrice(Killmail $killmail, ?string $rowKey, string $opType): array
    {
        $config = config('srp.payouts') ?? [];
        $rows = collect($config['rows'] ?? []);

        // Validate the operation type -> column; default to peacetime.
        if (! array_key_exists($opType, $config['operation_types'] ?? [])) {
            $opType = 'peacetime';
        }

        // Resolve the row: an explicit, valid pilot choice wins; otherwise
        // auto-classify the hull. classify() always returns a valid key.
        if (is_null($rowKey) || is_null($rows->firstWhere('key', $rowKey))) {
            $rowKey = ShipClassifier::classify($killmail->victim->ship_type_id ?? null);
        }

        $row = $rows->firstWhere('key', $rowKey);

        // Defensive: if the matrix somehow lacks the resolved key, fall back.
        if (is_null($row)) {
            $rowKey = ShipClassifier::FALLBACK;
            $row = $rows->firstWhere('key', $rowKey);
        }

        $price = $row ? (float) ($row[$opType] ?? 0) : 0.0;

        return [
            'price' => round($price, 2),
            'error' => 'None',
            'rule' => 'flat',
            'srp_type' => $opType,
            'srp_class' => $rowKey,
        ];
    }

    private function srpGetAdvancedPrice(Killmail $killmail, Collection $priceList): array
    {
        // Start by checking if there is a type rule that matches the ship
        $rule = AdvRule::where('type_id', $killmail->victim->ship_type_id)->first();
        if (is_null($rule)) {
            $rule = AdvRule::where('group_id', $killmail->victim->ship->groupID)->first();
            if (is_null($rule)) {
                return  $this->srpGetDefaultRulePrice($killmail, $priceList);
            }
        }

        return $this->srpGetRulePrice($rule, $killmail, $priceList);
    }

    private function srpGetRulePrice(AdvRule $rule, Killmail $killmail, Collection $priceList): array
    {

        $source = $rule->price_source;
        $base_value = $rule->base_value;
        $hull_percent = $rule->hull_percent / 100;
        $fit_percent = $rule->fit_percent / 100;
        $cargo_percent = $rule->cargo_percent / 100;
        $deduct_insurance = $rule->deduct_insurance;
        $price_cap = $rule->srp_price_cap;

        $deduct_insurance = $deduct_insurance == '1' ? true : false;

        foreach ($priceList as $item) {

            match ($item->getSRPCategory())
            {
                SRPCategoryEnum::SHIP => $item->setModifier($hull_percent),
                SRPCategoryEnum::CARGO => $item->setModifier($cargo_percent),
                SRPCategoryEnum::FITTING => $item->setModifier($fit_percent),
                SRPCategoryEnum::MISC => $item->setModifier(0),
            };
        }

        // Hydrate all the prices from the configured price provider.
        //
        // Pricing is optional. If no price provider is configured, or the provider
        // fails for any reason, we fall back to "manual pricing" (0 ISK) instead of
        // blocking the request. This lets pilots still submit an SRP request and
        // have the payout decided manually in-game.
        $value = 0;

        if (empty($source)) {
            logger()->info('SRP: no price provider configured — using manual pricing (0 ISK).');
        } else {
            try {
                PriceProviderSystem::getPrices($source, $priceList);

                $value = $priceList->sum(function (PriceableSRPItem $item) {
                    // Log::warning([$item->getTypeID(), $item->type()->typeName, $item->getPrice(), $item->getAmount(), $item->getSRPPrice()]);
                    return $item->getSRPPrice();
                });
            } catch (PriceProviderException $e) {
                logger()->warning('SRP: price provider failed — falling back to manual pricing (0 ISK). ' . $e->getMessage());
                $value = 0;
            }
        }
        // dd($priceList, $value);

        $total = $value + $base_value;

        if ($deduct_insurance) {
            $ins = Insurance::where('type_id', $killmail->victim->ship_type_id)->where('Name', 'Platinum')->first();
            if (! is_null($ins)) {
                $total = $total + $ins->cost - $ins->payout;
            }
        }

        $total = round($total, 2);

        //apply price cap
        if ($price_cap !== null && $total > $price_cap) {
            $total = $price_cap;
        }

        return [
            'price' => $total,
            'error' => 'None',
            'rule' => $rule->rule_type,
            'source' => $source,
            'base_value' => $base_value,
            'hull_percent' => $hull_percent,
            'fit_percent' => $fit_percent,
            'cargo_percent' => $cargo_percent,
            'deduct_insurance' => $deduct_insurance,
        ];
    }

    private function srpGetDefaultRulePrice(Killmail $killmail, Collection $priceList): array
    {

        $source = setting('cryptatech_seat_srp_advrule_def_source', true) ? setting('cryptatech_seat_srp_advrule_def_source', true) : 0;
        $base_value = setting('cryptatech_seat_srp_advrule_def_base', true) ? setting('cryptatech_seat_srp_advrule_def_base', true) : 0;
        $hull_percent = setting('cryptatech_seat_srp_advrule_def_hull', true) ? setting('cryptatech_seat_srp_advrule_def_hull', true) : 0;
        $fit_percent = setting('cryptatech_seat_srp_advrule_def_fit', true) ? setting('cryptatech_seat_srp_advrule_def_fit', true) : 0;
        $cargo_percent = setting('cryptatech_seat_srp_advrule_def_cargo', true) ? setting('cryptatech_seat_srp_advrule_def_cargo', true) : 0;
        $deduct_insurance = setting('cryptatech_seat_srp_advrule_def_ins', true) ? setting('cryptatech_seat_srp_advrule_def_ins', true) : 0;
        $price_cap = setting('cryptatech_seat_srp_advrule_def_price_cap', true) ? intval(setting('cryptatech_seat_srp_advrule_def_price_cap', true)) : null;

        $rule = new AdvRule([
            'rule_type' => 'default',
            'price_source' => $source,
            'base_value' => $base_value,
            'hull_percent' => $hull_percent,
            'cargo_percent' => $cargo_percent,
            'fit_percent' => $fit_percent,
            'srp_price_cap' => $price_cap,
            'deduct_insurance' => $deduct_insurance,
        ]);

        return $this->srpGetRulePrice($rule, $killmail, $priceList);

    }

    private function srpGetSimplePrice(Killmail $killmail, Collection $priceList): array
    {
        $rule = new AdvRule([
            'rule_type' => 'simple',
            'price_source' => setting('cryptatech_seat_srp_simple_source', true),
            'base_value' => 0,
            'hull_percent' => 100,
            'cargo_percent' => 100,
            'fit_percent' => 100,
            'deduct_insurance' => 0,
        ]);

        return $this->srpGetRulePrice($rule, $killmail, $priceList);
    }
}
