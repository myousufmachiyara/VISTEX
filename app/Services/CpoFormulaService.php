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
        $warping      = (float) ($inputs['warping'] ?? 1); // avoid div-by-zero; confirm default
        $warpShrinkagePct = (float) ($inputs['warp_shrinkage_pct'] ?? 0);
        $weftShrinkagePct = (float) ($inputs['weft_shrinkage_pct'] ?? 0);
        $warpYarnCostPrice = (float) ($inputs['warp_yarn_cost_price'] ?? 0);
        $weftYarnCostPrice = (float) ($inputs['weft_yarn_cost_price'] ?? 0);

        // 1. Reed Space — calculated, but editable (user override respected if provided)
        $reedSpace = isset($inputs['reed_space']) && $inputs['reed_space'] !== ''
            ? (float) $inputs['reed_space']
            : ($reed * $width / $reedCount);

        // 2. GSM
        $gsm = (($reed * 25.4) / $warpCount ) + (($pick * 25.4) / $weftCount);

        // 3. Warp Consumption
        $warpConsumption = ($reed * $width * 1.0936 / 840) / $warpCount + $warpShrinkagePct;

        // 4. Weft Consumption
        $weftConsumption = ($reedSpace * 1.0936 / 840) / $weftCount + $weftShrinkagePct;

        // 5. Total Yarn Weight Consumed
        $totalYarnWeightConsumed = ceil(($warpConsumption + $weftConsumption) * $totalMeters);

        // 6. Warp Yarn Rate (Rs/m)
        $warpYarnRate = $warpYarnCostPrice * $warpConsumption;

        // 7. Weft Yarn Rate (Rs/m)
        $weftYarnRate = $weftYarnCostPrice * $weftConsumption;

        // 8. Weaving Cost (per meter, from per-pick rate)
        $weavingCostPerMeter = $ratePerPick * $pick;

        // 9. Sizing Rate per Meter
        $sizingRatePerMeter = ($sizingLbs / $warping) * $warpConsumption;

        // 10. Actual Cost (per meter)
        $actualCostPerMeter = $weavingCostPerMeter + $sizingRatePerMeter + $warpYarnRate + $weftYarnRate;

        // Total weaving cost across all meters ordered
        $weavingCost = $actualCostPerMeter * $totalMeters;

        $itemName = sprintf('%sx%s/%s-%s-%s', $warpCount, $weftCount, $reed, $pick, $width);

        return [
            'reed_space'                  => round($reedSpace, 4),
            'gsm'                          => round($gsm, 3),
            'warp_consumption'             => round($warpConsumption, 6),
            'weft_consumption'             => round($weftConsumption, 6),
            'total_yarn_weight_consumed'   => $totalYarnWeightConsumed,
            'warp_yarn_rate'               => round($warpYarnRate, 4),
            'weft_yarn_rate'               => round($weftYarnRate, 4),
            'weaving_cost_per_meter'       => round($weavingCostPerMeter, 4),
            'sizing_rate_per_meter'        => round($sizingRatePerMeter, 4),
            'actual_cost_per_meter'        => round($actualCostPerMeter, 4),
            'weaving_cost'                 => round($weavingCost, 2),
            'item_name'                    => $itemName,
        ];
    }

    // 11. Net Amount = Actual Cost + GST
    public function withGst(array $calc, bool $gstApplicable, float $gstRate): array
    {
        $gstAmount = $gstApplicable ? round($calc['weaving_cost'] * ($gstRate / 100), 2) : 0;
        $calc['gst_amount'] = $gstAmount;
        $calc['net_amount'] = round($calc['weaving_cost'] + $gstAmount, 2);
        return $calc;
    }
}