<?php

namespace App\Http\Requests\Client;

use App\Http\Requests\Concerns\ValidatesAssignment;
use Illuminate\Foundation\Http\FormRequest;

abstract class ClientRequest extends FormRequest
{
    use ValidatesAssignment;
}
