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

        return redirect()->route('central.login');
    }
}
