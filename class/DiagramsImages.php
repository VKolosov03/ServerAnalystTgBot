<?php

class DiagramsImages
{
    private $image_charts;

    function __construct() {
        $this->image_charts = new ImageCharts();
    }

    public function createAvailableUsedPieChart($data)
    {
        $response = $this->image_charts->cht('p3')
        ->chs('300x300')
        ->chd('t:' . $data['used'] . ',' . $data['available'])
        ->chl('Використано: ' . $data['used'] . '|Вільно: ' . $data['available'])
        ->chf('ps0-0,lg,45,808080,0.2,808080,1|ps0-1,lg,45,a020f0,0.2,a020f0,1')
        ->toURL();

        return $response;
    }

    public function createUsageVerticalBarChart($data, $light_warning, $serious_warning)
    {
        $colors = [];

        foreach ($data as $bar) {
            if ($bar > $serious_warning) {
                $colors[] = 'ff0000';
            } elseif ($bar > $light_warning) {
                $colors[] = 'ffff00';
            } else {
                $colors[] = '00ff00';
            }
        }

        $response = $this->image_charts->cht('bvg')
            ->chs('300x300')
            ->chd('a:' . implode(',', $data))
            ->chl(implode('|', $data))
            ->chco(implode('|', $colors))
            ->toURL();

        return $response;
    }
}

