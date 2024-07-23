<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\DocumentModel;
use ThirdPartyPackage\CelestialBody;

class Planet extends CelestialBody
{
    use DocumentModel;

    protected $fillable = ['name', 'diameter'];
    protected $primaryKey = '_id';
    protected $keyType = 'string';
}
