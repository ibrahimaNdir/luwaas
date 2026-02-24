<?php

namespace App\Events;

use App\Models\Bail;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BailSigne
{
    use Dispatchable, SerializesModels;

    public $bail;

    public function __construct(Bail $bail)
    {
        $this->bail = $bail;
    }
}
