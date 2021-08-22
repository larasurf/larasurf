<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Carbon\Carbon;

trait HasTimer
{
    protected $start_time = null;
    protected $end_time = null;

    protected function startTimer()
    {
        $this->start_time = time();
    }

    protected function stopTimer()
    {
        $this->end_time = time();
    }

    protected function displayTimeElapsed()
    {
        $start_time = Carbon::createFromTimestamp($this->start_time);
        $end_time = Carbon::createFromTimestamp($this->end_time);
        $diff = $end_time->diffInSeconds($start_time);

        $this->line("<info>Done in:</info> {$diff}s");
    }
}
