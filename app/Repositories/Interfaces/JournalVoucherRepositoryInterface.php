<?php

namespace App\Repositories\Interfaces;

interface JournalVoucherRepositoryInterface
{
    public function store(array $data);
    public function getAll();
    public function find($id);
    public function update($id, array $data);
    public function delete($id);
}