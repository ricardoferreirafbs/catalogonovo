<?php

namespace App\Http\Controllers;

use App\Support\ErrorReference;
use Illuminate\Contracts\View\View;

class ErrorHelpController extends Controller
{
    public function __invoke(): View
    {
        return view('help.errors', [
            'references' => ErrorReference::all(),
        ]);
    }
}
