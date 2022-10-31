<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class PlayerExport implements FromArray
{
    /**
    * @return \Illuminate\Support\FromArray
    */

    protected $PlayerData;

    public function __construct(array $data)
    {
        $this->PlayerData = $data;
    }

    public function array() : array
    {
        return $this->PlayerData;
    }
}
