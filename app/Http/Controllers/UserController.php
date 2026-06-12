<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Division;
use App\Models\Office;
use App\Services\DigitalSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        // Exclude users explicitly marked as 'inactive'
        $users = User::with(['role', 'division.divisionchief', 'office'])
            ->select('id', 'name','sex', 'email', 'badge_id', 'role_id', 'position', 'specialization', 'division_id', 'office_id', 'profile_picture', 'electronic_signature', 'status', 'created_at')
            ->where('status', '<>', 'inactive')
            ->get();

        // For dropdowns
        $roles = Role::select('id', 'name')->get();
        $divisions = Division::where('status', 'active') // 👈 only active divisions
            ->select('id', 'division_name')
            ->get();

        // Offices for dependent dropdown
        $offices = Office::select('id', 'name', 'division_id')->get();

        return Inertia::render('Users/Index', [
            'users'     => $users,
            'roles'     => $roles,
            'divisions' => $divisions,
            'offices'   => $offices,
        ]);
    }

    /**
     * Show the employees list page (same data as users.index but labelled for HR/Employees).
     * Accessible to administrators only.
     */


    /**
     * Show users marked as inactive (Administrator only).
     */
    public function inactiveIndex()
    {
        $users = User::with(['role', 'division.divisionchief', 'office'])
            ->select('id', 'name','sex', 'email', 'badge_id', 'role_id', 'position', 'specialization', 'division_id', 'office_id', 'profile_picture', 'electronic_signature', 'created_at', 'status')
            ->where('status', 'inactive')
            ->get();

        $roles = Role::select('id', 'name')->get();
        $divisions = Division::where('status', 'active')
            ->select('id', 'division_name')
            ->get();
        $offices = Office::select('id', 'name', 'division_id')->get();

        return Inertia::render('Users/Index', [
            'users'     => $users,
            'roles'     => $roles,
            'divisions' => $divisions,
            'offices'   => $offices,
            'pageTitle' => 'Inactive Users',
            'headerTitle' => 'Inactive Users',
        ]);
    }

    /**
     * Store a new employee created by HR (minimal fields).
     * Allows HR to add employee records without assigning system credentials/roles.
     */

    /**
     * Return a lightweight JSON list of users for select/dropdowns.
     * Accessible to authenticated users.
     */
    public function selectList(Request $request)
    {
        $query = User::query()->select('id', 'name', 'badge_id', 'office_id')->where('status', '<>', 'inactive');

        // optional filter by emp_category or search
        if ($request->filled('emp_category')) {
            $query->where('emp_category', $request->input('emp_category'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('badge_id', 'like', "%{$q}%");
            });
        }

        $users = $query->orderBy('name')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'badge_id' => $u->badge_id,
                'office_id' => $u->office_id,
            ];
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sex'            => 'nullable|in:Male,Female',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'emp_category'   => 'nullable|in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
            'badge_id'       => ['nullable','string','max:64','regex:/^[A-Za-z0-9_\\-]+$/','unique:users,badge_id'],
            'employee_no'    => ['nullable','string','max:50','unique:users,employee_no'],
            'position'       => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:150',
            'division_id'    => 'nullable|exists:divisions,id',
            'office_id'      => 'nullable|exists:offices,id',
        ]);

        $user = User::create($data);
        if (empty($user->employee_no)) {
            $user->update(['employee_no' => 'pshscrc13-00' . $user->id]);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'sex'            => 'nullable|in:Male,Female',
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,' . $user->id,
            'emp_category'   => 'nullable|in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
            'badge_id'       => ['nullable','string','max:64','regex:/^[A-Za-z0-9_\\-]+$/','unique:users,badge_id,' . $user->id],
            'employee_no'    => ['nullable','string','max:50','unique:users,employee_no,' . $user->id],
            'position'       => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:150',
            'division_id'    => 'nullable|exists:divisions,id',
            'office_id'      => 'nullable|exists:offices,id',
            'status'         => 'nullable|in:active,inactive',
        ]);

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        // soft-delete by setting status to inactive so record is preserved
        $user->status = 'inactive';
        $user->save();

        return back()->with('success', 'User marked as inactive.');
    }

    /**
     * Reactivate a user previously marked inactive.
     */
    public function activate($id)
    {
        $user = User::findOrFail($id);
        $user->status = 'active';
        $user->save();

        return back()->with('success', 'User reactivated.');
    }

    public function uploadSignature(Request $request, User $user)
    {
        $request->validate([
            'signature_base64' => ['required', 'string', 'starts_with:data:image/png;base64,'],
        ]);

        app(DigitalSignatureService::class)->saveSignatureImage($user, $request->signature_base64);

        return redirect()->route('users.index')->with('success', 'Electronic signature uploaded.');
    }
}
