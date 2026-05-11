<?php

namespace App\Repositories\Interfaces;

interface ExpenseAndReceivableTrackerRepositoryInterface
{
    public function store(array $data);
    // public function getAll();
    public function find($id);
    public function update($id, array $data);
    public function delete($id);
     public function getQueryByType(int $type);
}