<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\OpeningMahasulFineSetupRepositoryInterface;
class OpeningMahasulFineSetupController extends Controller
{
     protected $repo;

    public function __construct(OpeningMahasulFineSetupRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Get current status
     */
    public function status()
    {
        return response()->json([
            'status' => 'success',
            'is_fine_applied' => $this->repo->getStatus()
        ]);
    }

    /**
     * Toggle status (true ↔ false)
     */
    public function toggle()
    {
        $this->repo->toggle();

        return response()->json([
            'status' => 'success',
            'message' => 'Fine status toggled successfully',
            'is_fine_applied' => $this->repo->getStatus()
        ]);
    }


    
}