<?php

namespace App\Events;

use App\Models\Bail;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BailCree
{
    use Dispatchable, SerializesModels;

    public $bail;

    /**
     * Quand le bailleur crée un nouveau bail
     */
    public function __construct(Bail $bail)
    {
        $this->bail = $bail;
    }
}
