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
        $warping      = (float) ($inputs['warping'] ?? 0);
        $warpShrinkagePct = (float) ($inputs['warp_shrinkage_pct'] ?? 0);
        $weftShrinkagePct = (float) ($inputs['weft_shrinkage_pct'] ?? 0);
        $warpYarnCostPrice = (float) ($inputs['warp_yarn_cost_price'] ?? 0);
        $weftYarnCostPrice = (float) ($inputs['weft_yarn_cost_price'] ?? 0);

        $reedSpace = isset($inputs['reed_space']) && $inputs['reed_space'] !== ''
            ? (float) $inputs['reed_space']
            : ($reed * $width / $reedCount);

        $warpGsm = ($reed * 25.4) / $warpCount;
        $weftGsm = ($pick * 25.4) / $weftCount;
        $gsm = $warpGsm + $weftGsm;

        $linearMeter = $width / 39.37;
        $gsmKg = $linearMeter * $gsm / 1000;

        $warpConsumptionBase = ($reed * $width * 1.0936 / 840) / $warpCount;
        $weftConsumptionBase = ($reedSpace * $pick * 1.0936 / 840) / $weftCount;

        $warpConsumption = $warpConsumptionBase * (1 + $warpShrinkagePct / 100);
        $weftConsumption = $weftConsumptionBase * (1 + $weftShrinkagePct / 100);

        $totalYarnWeightConsumed = $warpConsumption + $weftConsumption;

        $warpYarnRate = $warpYarnCostPrice * $warpConsumption;
        $weftYarnRate = $weftYarnCostPrice * $weftConsumption;
        $totalYarnCostPerMeter = $warpYarnRate + $weftYarnRate;

        $weavingCostPerMeter = $ratePerPick * $pick;

        // 9. Sizing Rate per Meter — mutually exclusive: Sizing (lbs) OR Warping
        if ($sizingLbs > 0) {
            $sizingRatePerMeter = $sizingLbs * $warpConsumption;
        } elseif ($warping > 0) {
            $sizingRatePerMeter = $warping * $warpConsumption;
        } else {
            $sizingRatePerMeter = 0;
        }

        $weavingPerMeter = $weavingCostPerMeter + $sizingRatePerMeter;
        $fabricCost = $weavingPerMeter + $warpYarnRate + $weftYarnRate;
        $weavingCost = $fabricCost * $totalMeters;

        $itemName = sprintf('%sx%s/%s-%s-%s', $warpCount, $weftCount, $reed, $pick, $width);

        return [
            'reed_space'                  => round($reedSpace, 2),
            'warp_gsm'                     => round($warpGsm, 2),
            'weft_gsm'                     => round($weftGsm, 2),
            'gsm'                          => round($gsm, 2),
            'gsm_kg'                       => round($gsmKg, 4),
            'warp_consumption'             => round($warpConsumption, 4),
            'weft_consumption'             => round($weftConsumption, 4),
            'total_yarn_weight_consumed'   => round($totalYarnWeightConsumed, 4),
            'warp_yarn_rate'               => round($warpYarnRate, 2),
            'weft_yarn_rate'               => round($weftYarnRate, 2),
            'total_yarn_cost_per_meter'    => round($totalYarnCostPerMeter, 2),
            'weaving_cost_per_meter'       => round($weavingCostPerMeter, 2),
            'sizing_rate_per_meter'        => round($sizingRatePerMeter, 2),
            'weaving_per_meter'            => round($weavingPerMeter, 2),
            'fabric_cost'                  => round($fabricCost, 2),
            'weaving_cost'                 => round($weavingCost, 2),
            'item_name'                    => $itemName,
        ];
    }

    public function withGst(array $calc, bool $gstApplicable, float $gstRate): array
    {
        $gstAmount = $gstApplicable ? round($calc['weaving_cost'] * ($gstRate / 100), 2) : 0;
        $calc['gst_amount'] = $gstAmount;
        $calc['net_amount'] = round($calc['weaving_cost'] + $gstAmount, 2);
        return $calc;
    }

}