<?php

namespace App\Filament\Lunar\Widgets;

use Filament\Support\RawJs;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderTotalsChart;

class VisoraOrderTotalsChart extends OrderTotalsChart
{
    protected static ?string $chartId = 'visoraOrderTotalsChart';

    protected static ?string $pollingInterval = null;

    protected function getOptions(): array
    {
        $options = parent::getOptions();

        $options['xaxis']['labels'] = [
            'rotate' => -35,
            'rotateAlways' => false,
            'hideOverlappingLabels' => true,
            'trim' => true,
            'minHeight' => 56,
            'maxHeight' => 72,
            'style' => [
                'fontSize' => '12px',
            ],
        ];

        return $options;
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
            {
                xaxis: {
                    labels: {
                        formatter: function (value) {
                            return String(value).slice(0, 3)
                        }
                    }
                }
            }
        JS);
    }
}
