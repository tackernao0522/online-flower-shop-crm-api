<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalesUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $totalSales;
    public $previousSales;
    public $changeRate;

    public function __construct($totalSales, $previousSales, $changeRate)
    {
        $this->totalSales = $totalSales;
        $this->previousSales = $previousSales;
        $this->changeRate = $changeRate;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('sales-stats'),
        ];
    }

    public function broadcastWith(): array
    {
        try {
            return [
                'totalSales' => $this->totalSales,
                'previousSales' => $this->previousSales,
                'changeRate' => $this->changeRate,
            ];
        } catch (\Throwable $error) {
            $exception = new \Exception($error->getMessage(), $error->getCode(), $error);
            $this->closeError($exception);
            throw $exception;
        }
    }
}
