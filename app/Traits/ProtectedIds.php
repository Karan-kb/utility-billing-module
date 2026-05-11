<?php

namespace App\Traits;

trait ProtectedIds
{
    protected function isProtectedId($id, $min, $max)
    {
        return $id >= $min && $id <= $max;
    }
}
