<?php

namespace Tests\Feature;

use App\Console\Kernel as BaseKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;

class TestKernel extends BaseKernel
{
    public function __construct(Application $app, Dispatcher $events)
    {
        parent::__construct($app, $events);
    }

    public function callSchedule($schedule)
    {
        $this->schedule($schedule);
    }

    public function callCommands()
    {
        $this->commands();
    }
}
