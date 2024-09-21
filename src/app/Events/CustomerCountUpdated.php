<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerCountUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $totalCount;
    public $previousTotalCount;
    public $changeRate;

    public function __construct($totalCount, $previousTotalCount, $changeRate)
    {
        $this->totalCount = $totalCount;
        $this->previousTotalCount = $previousTotalCount;
        $this->changeRate = $changeRate;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('customer-stats'),
        ];
    }

    public function broadcastWith(): array
    {
        try {
            return [
                'totalCount' => $this->totalCount,
                'previousTotalCount' => $this->previousTotalCount,
                'changeRate' => $this->changeRate,
            ];
        } catch (\Throwable $error) {
            $exception = new \Exception($error->getMessage(), $error->getCode(), $error);
            $this->closeError($exception);
            throw $exception;
        }
    }
}
