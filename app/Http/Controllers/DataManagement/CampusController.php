<?php

namespace App\Http\Controllers\DataManagement;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CampusController extends Controller
{
    public function index()
    {
        $campus = Campus::first();
        return Inertia::render('DataManagement/Campus/Index', [
            'campus' => $campus,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address'          => 'nullable|string',
            'telephone'        => 'nullable|string|max:20',
            'mobile'           => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'website'          => 'nullable|url|max:255',
            'facebook'         => 'nullable|string|max:255',
            'logo_base64'      => 'nullable|string',
            'logo_mime'        => 'nullable|string|in:image/jpeg,image/jpg,image/png,image/gif',
        ]);

        if (! empty($data['logo_base64'])) {
            $data['logo'] = $this->storeLogo($data['logo_base64'], $data['logo_mime'] ?? 'image/png');
        }

        unset($data['logo_base64'], $data['logo_mime']);

        Campus::create($data);

        return redirect()->route('campuses.index')->with('success', 'Campus created.');
    }

    public function update(Request $request, Campus $campus)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'code'             => 'nullable|string|max:100',
            'year_established' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
            'address'          => 'nullable|string',
            'telephone'        => 'nullable|string|max:20',
            'mobile'           => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'website'          => 'nullable|url|max:255',
            'facebook'         => 'nullable|string|max:255',
            'logo_base64'      => 'nullable|string',
            'logo_mime'        => 'nullable|string|in:image/jpeg,image/jpg,image/png,image/gif',
        ]);

        if (! empty($data['logo_base64'])) {
            // Delete old logo from S3
            if ($campus->logo) {
                Storage::disk('s3')->delete($campus->logo);
            }
            $data['logo'] = $this->storeLogo($data['logo_base64'], $data['logo_mime'] ?? 'image/png');
        }

        unset($data['logo_base64'], $data['logo_mime']);

        $campus->update($data);

        return redirect()->route('campuses.index')->with('success', 'Campus updated.');
    }

    public function destroy(Campus $campus)
    {
        if ($campus->logo) {
            Storage::disk('s3')->delete($campus->logo);
        }
        $campus->delete();
        return redirect()->route('campuses.index')->with('success', 'Campus deleted.');
    }

    private function storeLogo(string $base64, string $mime): string
    {
        $raw  = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $base64));
        $ext  = match (true) {
            str_contains($mime, 'png')  => 'png',
            str_contains($mime, 'gif')  => 'gif',
            default                     => 'jpg',
        };
        $path = 'campus_logos/' . uniqid('logo_') . '.' . $ext;
        Storage::disk('s3')->put($path, $raw);
        return $path;
    }
}
