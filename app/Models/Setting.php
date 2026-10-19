<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/** One value the user changed in Ajustes. Read it through App\Services\Settings. */
#[Fillable(['key', 'value'])]
#[WithoutIncrementing]
#[WithoutTimestamps]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';
}
