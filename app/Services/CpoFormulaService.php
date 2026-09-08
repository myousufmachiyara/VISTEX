<?php

namespace App\Services;

class CpoFormulaService
{
    public function calculate(array $inputs): array
    {
        $reed         = (float) $inputs['reed'];
        $reedCount    = (float) $inputs['reed_count'];
        $warpCount    = (float) $inputs['warp_count'];
        $weftCount    = (float) $inputs['weft_count'];
        $pick         = (float) $inputs['pick'];
        $width        = (float) $inputs['width'];
        $totalMeters  = (float) $inputs['total_meters_required'];
        $ratePerPick  = (float) $inputs['rate_per_pick'];
        $sizingLbs    = (float) ($inputs['sizing_lbs'] ?? 0);
        $warping      = (float) ($inputs['warping'] ?? 1);
        $warpShrinkagePct = (float) ($inputs['warp_shrinkage_pct'] ?? 0);
        $weftShrinkagePct = (float) ($inputs['weft_shrinkage_pct'] ?? 0);
        $warpYarnCostPrice = (float) ($inputs['warp_yarn_cost_price'] ?? 0);
        $weftYarnCostPrice = (float) ($inputs['weft_yarn_cost_price'] ?? 0);

        // 1. Reed Space — calculated, but editable
        $reedSpace = isset($inputs['reed_space']) && $inputs['reed_space'] !== ''
            ? (float) $inputs['reed_space']
            : ($reed * $width / $reedCount);

        // 2. GSM — broken into Warp GSM + Weft GSM
        $warpGsm = ($reed * 25.4) / $warpCount;
        $weftGsm = ($pick * 25.4) / $weftCount;
        $gsm = $warpGsm + $weftGsm;

        // Linear Meter = Width (inches) / 39.37
        $linearMeter = $width / 39.37;

        // GSM in Kg = Linear Meter × GSM / 1000
        $gsmKg = $linearMeter * $gsm / 1000;

        // 3, 4 Warp & Weft Consumption
        $warpConsumptionBase = ($reed * $width * 1.0936 / 840) / $warpCount;
        $weftConsumptionBase = ($reedSpace * $pick * 1.0936 / 840) / $weftCount;

        $warpConsumption = $warpConsumptionBase * (1 + $warpShrinkagePct / 100);
        $weftConsumption = $weftConsumptionBase * (1 + $weftShrinkagePct / 100);

        // 5. Total Yarn Weight Consumed
        $totalYarnWeightConsumed = $warpConsumption + $weftConsumption;

        // 6. Warp Yarn Rate (Rs/m)
        $warpYarnRate = $warpYarnCostPrice * $warpConsumption;

        // 7. Weft Yarn Rate (Rs/m)
        $weftYarnRate = $weftYarnCostPrice * $weftConsumption;

        // 8. Weaving Cost (per meter, from per-pick rate)
        $weavingCostPerMeter = $ratePerPick * $pick;

        // 9. Sizing Rate per Meter
        $sizingRatePerMeter = ($sizingLbs / $warping) * $warpConsumption;

        // 10. Weaving Per Meter
        $weavingPerMeter = $weavingCostPerMeter + $sizingRatePerMeter + $warpYarnRate + $weftYarnRate;

        // Total weaving cost across all meters ordered
        $weavingCost = $weavingPerMeter * $totalMeters;

        $itemName = sprintf('%sx%s/%s-%s-%s', $warpCount, $weftCount, $reed, $pick, $width);

        return [
            'reed_space'                  => round($reedSpace, 2),
            'warp_gsm'                     => round($warpGsm, 2),
            'weft_gsm'                     => round($weftGsm, 2),
            'gsm'                          => round($gsm, 2),
            'gsm_kg'                       => round($gsmKg, 4),
            'warp_consumption'             => round($warpConsumption, 4),
            'weft_consumption'             => round($weftConsumption, 4),
            'total_yarn_weight_consumed'   => $totalYarnWeightConsumed,
            'warp_yarn_rate'               => round($warpYarnRate, 2),
            'weft_yarn_rate'               => round($weftYarnRate, 2),
            'weaving_cost_per_meter'       => round($weavingCostPerMeter, 2),
            'sizing_rate_per_meter'        => round($sizingRatePerMeter, 2),
            'weaving_per_meter'            => round($weavingPerMeter, 2),
            'weaving_cost'                 => round($weavingCost, 2),
            'item_name'                    => $itemName,
        ];
    }

    // 11. Net Amount = Weaving Cost + GST
    public function withGst(array $calc, bool $gstApplicable, float $gstRate): array
    {
        $gstAmount = $gstApplicable ? round($calc['weaving_cost'] * ($gstRate / 100), 2) : 0;
        $calc['gst_amount'] = $gstAmount;
        $calc['net_amount'] = round($calc['weaving_cost'] + $gstAmount, 2);
        return $calc;
    }
}