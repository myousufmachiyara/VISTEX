<?php

namespace App\Services;

class CpoFormulaService
{
    // $inputs: warp_count, weft_count, reed_count, pick, width,
    //          total_meters_required, rate_per_pick, sizing_lbs, warp_conversion_pct
    public function calculate(array $inputs): array
    {
        $warpCount    = (float) $inputs['warp_count'];
        $weftCount    = (float) $inputs['weft_count'];
        $reedCount    = (float) $inputs['reed_count'];
        $pick         = (float) $inputs['pick'];
        $width        = (float) $inputs['width'];
        $totalMeters  = (float) $inputs['total_meters_required'];
        $ratePerPick  = (float) $inputs['rate_per_pick'];
        $sizingLbs    = (float) ($inputs['sizing_lbs'] ?? 0);
        $warpConvPct  = (float) ($inputs['warp_conversion_pct'] ?? 0);

        $reedSpace = $width;

        $gsm = (($reedCount * 25.4) / $warpCount) + (($pick * 25.4) / $weftCount);

        $warpConsumption = ((($reedCount * $width * 1.0936) / 840) / $warpCount) + $warpConvPct;

        $weftConsumption = (((($reedCount * ($width / $reedCount)) * $pick * 1.0936) / 840) / $weftCount) * 0.02;

        $totalGreigeQtyRequired = $warpConsumption + $weftConsumption;

        $totalYarnWeightConsumed = $totalGreigeQtyRequired * $totalMeters;

        $ratePerMeter = $pick * $ratePerPick;

        $sizingPerMeter = $warpConsumption * $sizingLbs;

        $weavingRate = $ratePerMeter + $sizingPerMeter;

        $weavingCost = $weavingRate * $totalMeters;

        $itemName = sprintf('%sx%s/%s-%s-%s', $warpCount, $weftCount, $reedCount, $pick, $width);

        return [
            'reed_space'                  => round($reedSpace, 4),
            'gsm'                          => round($gsm, 3),
            'warp_consumption'             => round($warpConsumption, 6),
            'weft_consumption'             => round($weftConsumption, 6),
            'total_greige_qty_required'    => round($totalGreigeQtyRequired, 6),
            'total_yarn_weight_consumed'   => round($totalYarnWeightConsumed, 3),
            'rate_per_meter'               => round($ratePerMeter, 4),
            'sizing_per_meter'             => round($sizingPerMeter, 4),
            'weaving_rate'                 => round($weavingRate, 4),
            'weaving_cost'                 => round($weavingCost, 2),
            'item_name'                    => $itemName,
        ];
    }

    public function withGst(array $calc, bool $gstApplicable, float $gstRate): array
    {
        $gstAmount = $gstApplicable ? round($calc['weaving_cost'] * ($gstRate / 100), 2) : 0;
        $netAmount = round($calc['weaving_cost'] + $gstAmount, 2);

        return array_merge($calc, [
            'gst_amount' => $gstAmount,
            'net_amount' => $netAmount,
        ]);
    }
}