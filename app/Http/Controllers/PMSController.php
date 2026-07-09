<?php

namespace App\Http\Controllers;

use App\Models\PMS;
use App\Models\User;
use App\Models\ICTEquipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PMSController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->input('search');
        $status    = $request->input('status');
        $frequency = $request->input('frequency');

        $query = PMS::with([
            'performedBy:id,name',
            'equipments' => function ($q) {
                $q->select(
                    'ict_equipments.id',
                    'description',
                    'room_id',
                    'owner_id',
                    'serial_no',
                    'category'
                )
                ->with([
                    'room:id,name,code',
                    'owner:id,name',
                ]);
            },
            'dates',
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('office_area', 'like', "%{$search}%")
                  ->orWhere('school_year', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($frequency) {
            $query->where('frequency', $frequency);
        }

        $pmsSchedules = $query->paginate(15)->withQueryString();

        $users = User::select('id','name')->orderBy('name')->get();

        $equipments = ICTEquipment::with([
            'room:id,name,code',
            'owner:id,name',
        ])->orderBy('description')->get();

        return Inertia::render('ITJobRequests/PMS', [
            'pmsSchedules' => $pmsSchedules,
            'users'        => $users,
            'equipments'   => $equipments,
            'filters'      => $request->only('search', 'status', 'frequency'),
        ]);
    }



    /**
     * Validate schedule payload, ensuring school_year is well-formed (YYYY-YYYY,
     * consecutive years) and every schedule date falls within that school year.
     */
    private function validateScheduleData(Request $request): array
    {
        return $request->validate([
            'title'                  => 'required|string|max:255',
            'frequency'              => 'required|string|max:255',
            'school_year'            => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/', function ($attribute, $value, $fail) {
                if (preg_match('/^(\d{4})-(\d{4})$/', $value, $m) && (int) $m[2] !== (int) $m[1] + 1) {
                    $fail('School year must span two consecutive years (e.g. 2025-2026).');
                }
            }],
            'office_area'            => 'required|string|max:255',  // ✅ new
            'status'                 => 'required|string|max:255',
            'remarks'                => 'nullable|string',
            'schedule_dates'         => 'required|array',
            'schedule_dates.*.date'  => ['required', 'date', function ($attribute, $value, $fail) use ($request) {
                if (!preg_match('/^(\d{4})-(\d{4})$/', (string) $request->input('school_year'), $m)) {
                    return;
                }
                [$rangeStart, $rangeEnd] = ["{$m[1]}-01-01", "{$m[2]}-12-31"];
                if ($value < $rangeStart || $value > $rangeEnd) {
                    $fail("The scheduled date must fall within the school year {$request->input('school_year')}.");
                }
            }],
        ], [
            'school_year.regex' => 'School year must be in the format YYYY-YYYY (e.g. 2025-2026).',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateScheduleData($request);

        // Create PMS
        $pms = PMS::create([
            'title'        => $data['title'],
            'frequency'    => $data['frequency'],
            'school_year'  => $data['school_year'], // ✅
            'office_area'  => $data['office_area'],      // ✅
            'status'       => $data['status'],
            'remarks'      => $data['remarks'] ?? null,
            'performed_by' => Auth::id(),
        ]);

        // Store multiple schedule dates
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        return redirect()->back()->with('success', 'PMS Schedule added successfully.');
    }

    public function update(Request $request, PMS $pms)
    {
        $data = $this->validateScheduleData($request);

        // Update PMS
        $pms->update([
            'title'        => $data['title'],
            'frequency'    => $data['frequency'],
            'school_year'  => $data['school_year'], // ✅
            'office_area'  => $data['office_area'],      // ✅
            'status'       => $data['status'],
            'remarks'      => $data['remarks'] ?? null,
            'performed_by' => Auth::id(),
        ]);

        // Replace old dates with new ones
        $pms->dates()->delete();
        foreach ($data['schedule_dates'] as $date) {
            $pms->dates()->create([
                'schedule_date' => $date['date'],
            ]);
        }

        return redirect()->back()->with('success', 'PMS Schedule updated successfully.');
    }

    public function destroy(PMS $pms)
    {
        $pms->delete();
        return redirect()->back()->with('success', 'PMS Schedule deleted successfully.');
    }

    public function assignEquipments(Request $request, $pmsId)
    {
        $data = $request->validate([
            'equipment_ids'   => 'required|array',
            'equipment_ids.*' => 'exists:ict_equipments,id',
        ]);

        $pms = PMS::findOrFail($pmsId);
        $pms->equipments()->syncWithoutDetaching($data['equipment_ids']);

        return redirect()->back()->with('success', 'Equipments assigned to PMS successfully.');
    }

    public function showEquipments(PMS $pms)
    {
        // Load equipments with histories and related room + owner, also PMS dates
        $pms->load([
            'equipments.histories', 
            'equipments.room:id,name,code',   // room for location
            'equipments.owner:id,name',       // optional, if needed
            'dates',
        ]);

        // Map schedule dates
        $pms->schedule_dates = $pms->dates->map(fn($d) => [
            'schedule_date' => $d->schedule_date,
            'status'        => $d->status,
        ]);

        // Map equipments with histories and room location
        $equipments = $pms->equipments->map(function ($eq) {
            return [
                'id'            => $eq->id,
                'description'   => $eq->description,
                'serial_no'     => $eq->serial_no,
                'category'      => $eq->category,
                'room'          => $eq->room ? $eq->room->code : 'No Location',
                'owner'         => $eq->owner ? $eq->owner->name : 'No Owner',
                'history_dates' => $eq->histories->pluck('pms_date'),
            ];
        });

        return Inertia::render('ITJobRequests/PMSEquipments', [
            'pms'        => $pms,
            'equipments' => $equipments,
        ]);
    }




}
