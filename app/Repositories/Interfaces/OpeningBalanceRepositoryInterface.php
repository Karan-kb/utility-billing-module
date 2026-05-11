<?php

namespace App\Repositories\Interfaces;
interface OpeningBalanceRepositoryInterface
{
    public function store(array $data);
    public function getAll();
    public function getByFiscalYear();  
    public function changeAll(array $data);
    public function deleteAll();
}