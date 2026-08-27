<?php

namespace App\Services;

use TCPDF;

class myPDF extends TCPDF
{
    private $tableHtml = ''; // repeating table header for multi-page documents

    public function setTableHtml($html)
    {
        $this->tableHtml = $html;
    }

    public function Header()
    {
        $this->SetFont('helvetica', 'B', 10);
        $this->Cell(0, 10, '', 0, 1, 'C');
        if ($this->getPage() > 1) {
            $this->SetMargins(10, 17, 10);
            $this->setCellPadding(1.2);
            $this->writeHTML($this->tableHtml, true, false, true, false, '');
        }
    }

    public function Footer()
    {
        $this->SetY(-15);

        $this->SetFont('helvetica', 'I', 10);
        $this->SetTextColor(23, 54, 93);

        $pageWidth = $this->getPageWidth();

        $companyName = 'VISTEX Pvt. Ltd.';
        $websiteLink = 'vistexind.com';
        $pageNumber  = 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages();

        $leftTextWidth   = $this->GetStringWidth($websiteLink);
        $centerTextWidth = $this->GetStringWidth($companyName);
        $rightTextWidth  = $this->GetStringWidth($pageNumber);

        $leftX   = 10;
        $centerX = ($pageWidth - $centerTextWidth) / 2;
        $rightX  = $pageWidth - 34;

        $this->SetLineWidth(1);
        $this->SetDrawColor(23, 54, 93);
        $this->Line(10, $this->getY(), $pageWidth - 10, $this->getY());

        $this->SetFont('helvetica', 'B', 13);
        $this->SetX($leftX);
        $this->Cell($leftTextWidth, 10, $websiteLink, 0, 0, 'L');

        $this->SetFont('helvetica', 'B', 13);
        $this->SetX($centerX);
        $this->Cell($centerTextWidth, 10, $companyName, 0, 0, 'C');

        $this->SetFont('helvetica', 'B', 13);
        $this->SetX($rightX);
        $this->Cell($rightTextWidth, 10, $pageNumber, 0, 0, 'R');
    }

    function convertCurrencyToWords($number)
    {
        $Thousand = 1000;
        $Million  = $Thousand * $Thousand;
        $Billion  = $Thousand * $Million;
        $Trillion = $Thousand * $Billion;

        if ($number == 0) {
            return "Zero Rupees Only";
        }

        $isNegative = $number < 0;
        $number = abs($number);

        $result = "";

        if ($number >= $Trillion) {
            $result .= $this->convertDigitGroup(floor($number / $Trillion)) . " Trillion ";
            $number %= $Trillion;
        }
        if ($number >= $Billion) {
            $result .= $this->convertDigitGroup(floor($number / $Billion)) . " Billion ";
            $number %= $Billion;
        }
        if ($number >= $Million) {
            $result .= $this->convertDigitGroup(floor($number / $Million)) . " Million ";
            $number %= $Million;
        }
        if ($number >= $Thousand) {
            $result .= $this->convertDigitGroup(floor($number / $Thousand)) . " Thousand ";
            $number %= $Thousand;
        }
        if ($number > 0) {
            $result .= $this->convertDigitGroup($number);
        }

        $result = trim($result) . " Rupees Only";

        return $isNegative ? "Negative " . $result : $result;
    }

    function convertDigitGroup($number)
    {
        $hundreds  = floor($number / 100);
        $remainder = $number % 100;
        $result    = "";

        $singles = [1=>"One",2=>"Two",3=>"Three",4=>"Four",5=>"Five",6=>"Six",7=>"Seven",8=>"Eight",9=>"Nine"];
        if ($number <= 9 && isset($singles[$number])) {
            return $singles[$number];
        }

        if ($hundreds > 0) {
            $result .= $this->convertSingleDigit($hundreds) . " Hundred ";
        }

        if ($remainder > 0) {
            if ($remainder < 20) {
                $result .= $this->convertTens($remainder);
            } else {
                $result .= $this->convertTens(floor($remainder / 10) * 10);
                if ($remainder % 10 > 0) {
                    $result .= "-" . $this->convertSingleDigit($remainder % 10);
                }
            }
        }

        return trim($result);
    }

    function convertSingleDigit($digit)
    {
        $digits = [0=>"",1=>"One",2=>"Two",3=>"Three",4=>"Four",5=>"Five",6=>"Six",7=>"Seven",8=>"Eight",9=>"Nine"];
        return $digits[$digit] ?? "";
    }

    function convertTens($number)
    {
        $tens = [
            10=>"Ten",11=>"Eleven",12=>"Twelve",13=>"Thirteen",14=>"Fourteen",
            15=>"Fifteen",16=>"Sixteen",17=>"Seventeen",18=>"Eighteen",19=>"Nineteen",
            20=>"Twenty",30=>"Thirty",40=>"Forty",50=>"Fifty",60=>"Sixty",
            70=>"Seventy",80=>"Eighty",90=>"Ninety",
        ];
        return $tens[$number] ?? "";
    }
}