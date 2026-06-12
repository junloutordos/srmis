<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Central\InstanceSettings;

class LandingController extends Controller
{
    public function index(InstanceSettings $settings)
    {
        if (! $settings->isInstalled()) {
            return redirect()->route('setup.index');
        }

        if (auth('central')->check()) {
            return redirect()->route('central.admin');
        }

        // Campus users land on the shared login page; the campus is inferred
        // from their email address at sign-in.
        return redirect()->route('login');
    }
}
