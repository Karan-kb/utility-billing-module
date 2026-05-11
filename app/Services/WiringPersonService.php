<?php

namespace App\Services;

use App\Models\WiringPerson;

class WiringPersonService
{
    public function list()
    {
        return WiringPerson::orderBy('id', 'DESC')->get();
    }

    public function create(array $data)
    {
        return WiringPerson::create($data);
    }

    public function update(WiringPerson $person, array $data)
    {
        $person->update($data);
        return $person;
    }

    public function delete(WiringPerson $person)
    {
        return $person->delete();
    }
}
