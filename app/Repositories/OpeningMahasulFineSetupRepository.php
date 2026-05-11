<?php
namespace App\Repositories;

use App\Models\OpeningMahasulFineSetup;
use App\Repositories\Interfaces\OpeningMahasulFineSetupRepositoryInterface;

class OpeningMahasulFineSetupRepository implements OpeningMahasulFineSetupRepositoryInterface
{
    protected $model;

    public function __construct(OpeningMahasulFineSetup $model)
    {
        $this->model = $model;
    }

    // Always fetch ID = 1
    private function getRow()
    {
        return $this->model->findOrFail(1);
    }

    public function getStatus(): bool
    {
        return (bool) $this->getRow()->is_fine_applied;
    }

    public function toggle(): bool
    {
        $row = $this->getRow();

        return $row->update([
            'is_fine_applied' => !$row->is_fine_applied
        ]);
    }
}