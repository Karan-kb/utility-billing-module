<?php

namespace App\Repositories\Interfaces;
interface OpeningMahasulFineSetupRepositoryInterface
{
        public function getStatus(): bool;

    public function toggle(): bool;
}