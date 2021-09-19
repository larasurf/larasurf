<?php

namespace LaraSurf\LaraSurf\Commands\Traits;

use Carbon\Carbon;

trait HasTimer
{
    /**
     * The timestamp for when the timer was started.
     *
     * @var int|null
     */
    protected ?int $start_time = null;

    /**
     * The timestamp for when the timer was stopped.
     *
     * @var int|null
     */
    protected ?int $end_time = null;

    /**
     * Start the timer.
     */
    protected function startTimer()
    {
        $this->start_time = time();
    }

    /**
     * Stop the timer.
     */
    protected function stopTimer()
    {
        $this->end_time = time();
    }

    /**
     * Display the time elapsed in a human-friendly manner.
     */
    protected function displayTimeElapsed()
    {
        $start_time = Carbon::createFromTimestamp($this->start_time);
        $end_time = Carbon::createFromTimestamp($this->end_time);
        $diff = $end_time->diffInSeconds($start_time);

        $this->line("<info>Done in:</info> {$diff}s");
    }
}
