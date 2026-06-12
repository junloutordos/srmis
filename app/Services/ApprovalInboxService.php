<?php

namespace App\Services;

use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\WorkRequest;
use App\Models\ServiceRequest;
use App\Models\MessengerialRequest;
use App\Models\Division;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalInboxService
{
    public function __construct(private User $user) {}

    /**
     * Sum all role-applicable pending counts for the sidebar badge.
     */
    public function totalPendingCount(): int
    {
        $total = 0;
        foreach ($this->getPendingItems() as $tab) {
            $total += $tab['count'];
        }
        return $total;
    }

    /**
     * Build the full tabs array with normalised items for each role.
     * Tabs with zero items are excluded.
     */
    public function getPendingItems(): array
    {
        $user = $this->user;
        $tabs = [];

        $isDivisionChief = $user->hasRole('DivisionChief');
        $isFADChief      = str_contains($user->position ?? '', 'FAD') || $user->hasRole('FAD Chief');
        $isGSUHead       = $user->hasRole('GSU Head');
        $isOCD           = $user->hasRole('OCD');
        $isAdmin         = $user->hasRole('Administrator');

        // Administrator sees all pending items across every module (union of all roles)
        if ($isAdmin) {
            $isDivisionChief = true;
            $isFADChief      = true;
            $isGSUHead       = true;
            $isOCD           = true;
        }

        // Pre-compute division IDs for DC queries (used in multiple places)
        $divisionIds = [];
        if ($isDivisionChief && ! $isAdmin) {
            $divisionIds = Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
        } elseif ($isAdmin) {
            // Admin sees all divisions
            $divisionIds = Division::pluck('id')->toArray();
        }

        // ── Division Chief (and Administrator) ───────────────────────────────
        if ($isDivisionChief) {
            // IT Job Requests
            $itQuery = ITJobRequest::with(['user:id,name', 'divisionChief:id,name', 'assignedTo:id,name'])
                ->where('status', 'Pending Division Chief Approval');
            if (! $isAdmin) {
                $itQuery->where('divisionchief_id', $user->id);
            }
            $itItems = $itQuery->latest()->get()
                ->map(fn($r) => $this->normaliseITJobRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'it_job_requests', 'IT Job Requests', $itItems);

            // Vehicle Requests
            $vrQuery = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Pending');
            if (! $isAdmin) {
                $vrQuery->where(function ($q) use ($user, $divisionIds) {
                    $q->where('division_chief_id', $user->id);
                    if (!empty($divisionIds)) {
                        $q->orWhereHas('requester', fn($r) => $r->whereIn('division_id', $divisionIds));
                    }
                });
            }
            $vrItems = $vrQuery->latest()->get()
                ->map(fn($r) => $this->normaliseVehicleRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrItems);

            // Facility Requests
            $frQuery = FacilityRequest::with('requester:id,name,division_id')
                ->where('status', 'Pending');
            if (! $isAdmin && !empty($divisionIds)) {
                $frQuery->whereHas('requester', fn($q) => $q->whereIn('division_id', $divisionIds));
            }
            if (! $isAdmin && empty($divisionIds)) {
                $frQuery->whereRaw('0 = 1');
            }
            $frItems = $frQuery->latest()->get()
                ->map(fn($r) => $this->normaliseFacilityRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'facility_requests', 'Facility Requests', $frItems);

            // Work Requests
            $wrQuery = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->where('status', 'Pending');
            if (! $isAdmin) {
                $wrQuery->where(function ($q) use ($user, $divisionIds) {
                    $q->where('division_chief_id', $user->id);
                    if (!empty($divisionIds)) {
                        $q->orWhereHas('requester', fn($r) => $r->whereIn('division_id', $divisionIds));
                    }
                });
            }
            $wrItems = $wrQuery->latest()->get()
                ->map(fn($r) => $this->normaliseWorkRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'work_requests', 'Work Requests', $wrItems);

            // Service Requests
            $srQuery = ServiceRequest::with('requester:id,name,division_id')
                ->where('status', 'Pending');
            if (! $isAdmin && !empty($divisionIds)) {
                $srQuery->whereHas('requester', fn($q) => $q->whereIn('division_id', $divisionIds));
            }
            if (! $isAdmin && empty($divisionIds)) {
                $srQuery->whereRaw('0 = 1');
            }
            $srItems = $srQuery->latest()->get()
                ->map(fn($r) => $this->normaliseServiceRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'service_requests', 'Service Requests', $srItems);

            // Messengerial Requests
            $mrQuery = MessengerialRequest::where('status', 'Pending Division Chief Approval');
            if (! $isAdmin) {
                $mrQuery->where('division_chief_id', $user->id);
            }
            $mrItems = $mrQuery->latest()->get()
                ->map(fn($r) => $this->normaliseMessengerialRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'messengerial_requests', 'Messengerial Requests', $mrItems);

            // Gate Passes (raw DB — no Eloquent model)
        }

        // ── FAD Chief ─────────────────────────────────────────────────────────
        if ($isFADChief) {
            $frFAD = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending FAD Approval')->latest()->get()
                ->map(fn($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frFAD);

            $wrFAD = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->whereIn('status', ['GSU Approved', 'Pending FAD Approval'])->latest()->get()
                ->map(fn($r) => $this->normaliseWorkRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'work_requests', 'Work Requests', $wrFAD);

            $srFAD = ServiceRequest::with('requester:id,name')
                ->where('status', 'Approved')->latest()->get()
                ->map(fn($r) => $this->normaliseServiceRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'service_requests', 'Service Requests', $srFAD);
        }

        // ── GSU Head ──────────────────────────────────────────────────────────
        if ($isGSUHead) {
            $vrGSU = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Approved')->whereNull('driver_id')->latest()->get()
                ->map(fn($r) => $this->normaliseVehicleRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrGSU);

            $frGSU = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending FAD Approval')->latest()->get()
                ->map(fn($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frGSU);

            $wrGSU = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->where('status', 'GSU Approved')->latest()->get()
                ->map(fn($r) => $this->normaliseWorkRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'work_requests', 'Work Requests', $wrGSU);
        }

        // ── OCD ───────────────────────────────────────────────────────────────
        if ($isOCD) {
            $itOCD = ITJobRequest::with(['user:id,name', 'divisionChief:id,name', 'assignedTo:id,name'])
                ->where('status', 'Pending OCD Approval')->latest()->get()
                ->map(fn($r) => $this->normaliseITJobRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'it_job_requests', 'IT Job Requests', $itOCD);

            $vrOCD = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Approved')->latest()->get()
                ->map(fn($r) => $this->normaliseVehicleRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrOCD);

            $frOCD = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending OCD Approval')->latest()->get()
                ->map(fn($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frOCD);

            // Messengerial: OCD acts as Division Chief for OCD-division requestors only.
            // Those requests are already covered by the DC block (division_chief_id = $user->id).
            // No separate OCD messengerial queue here.

        }


        // Return only tabs with items, re-indexed
        return array_values(array_filter($tabs, fn($t) => $t['count'] > 0));
    }

    // ── Tab helpers ───────────────────────────────────────────────────────────

    private function addTab(array &$tabs, string $type, string $label, array $items): void
    {
        if (!isset($tabs[$type])) {
            $tabs[$type] = ['type' => $type, 'label' => $label, 'count' => 0, 'items' => []];
        }
        $tabs[$type]['items'] = array_merge($tabs[$type]['items'], $items);
        $tabs[$type]['count'] = count($tabs[$type]['items']);
    }

    private function mergeOrAddTab(array &$tabs, string $type, string $label, array $items): void
    {
        $this->addTab($tabs, $type, $label, $items);
    }

    // ── Normalisers ───────────────────────────────────────────────────────────

    private function normaliseITJobRequest(ITJobRequest $r): array
    {
        return [
            'id'             => $r->id,
            'type'           => 'it_job_requests',
            'reference_no'   => $r->itjr_no ?? "#{$r->id}",
            'requester_name' => $r->user?->name ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->title ?? $r->category ?? '—',
            'sections'       => [
                [
                    'title'  => 'Request Information',
                    'fields' => [
                        ['label' => 'ITJR No.',      'value' => $r->itjr_no ?? '—'],
                        ['label' => 'Category',      'value' => $r->category ?? '—'],
                        ['label' => 'Title',         'value' => $r->title ?? '—'],
                        ['label' => 'Event Date',    'value' => $r->event_date?->format('M d, Y') ?? '—'],
                        ['label' => 'Status',        'value' => $r->status],
                        ['label' => 'Filed At',      'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title'  => 'Description',
                    'fields' => [
                        ['label' => 'Description', 'value' => $r->description ?? '—', 'full' => true],
                    ],
                ],
                [
                    'title'  => 'Assignment',
                    'fields' => [
                        ['label' => 'Assigned To',     'value' => $r->assignedTo?->name ?? '—'],
                        ['label' => 'Division Chief',  'value' => $r->divisionChief?->name ?? '—'],
                        ['label' => 'MIS Assessment',  'value' => $r->mis_assessment ?? '—', 'full' => true],
                        ['label' => 'Recommendation',  'value' => $r->recommendation ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normaliseVehicleRequest(VehicleRequest $r): array
    {
        $multipleDates = $r->date_needed_multiple
            ? implode(', ', array_map(fn($d) => \Carbon\Carbon::parse($d)->format('M d, Y'), $r->date_needed_multiple))
            : null;

        return [
            'id'             => $r->id,
            'type'           => 'vehicle_requests',
            'reference_no'   => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->purpose ?? '—',
            'sections'       => [
                [
                    'title'  => 'Trip Details',
                    'fields' => [
                        ['label' => 'Purpose',           'value' => $r->purpose ?? '—', 'full' => true],
                        ['label' => 'Destination',       'value' => $r->destination ?? '—', 'full' => true],
                        ['label' => 'Date Needed',       'value' => $r->date_needed?->format('M d, Y') ?? '—'],
                        ['label' => 'Multiple Dates',    'value' => $multipleDates ?? '—'],
                        ['label' => 'Time of Departure', 'value' => $r->time_of_departure ?? '—'],
                        ['label' => 'ETA',               'value' => $r->eta ?? '—'],
                    ],
                ],
                [
                    'title'  => 'Vehicle & Passengers',
                    'fields' => [
                        ['label' => 'Vehicle Type',    'value' => $r->vehicle_type ?? '—'],
                        ['label' => 'No. of Passengers', 'value' => $r->passengers ?? '—'],
                        ['label' => 'Division Chief',  'value' => $r->divisionChief?->name ?? '—'],
                        ['label' => 'Status',          'value' => $r->status],
                        ['label' => 'Filed At',        'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
            ],
        ];
    }

    private function normaliseFacilityRequest(FacilityRequest $r): array
    {
        // Resolve venue IDs to facility names
        $venueIds = is_array($r->venue) ? $r->venue : [];
        $venueNames = '—';
        if (!empty($venueIds)) {
            $names = \App\Models\Facility::whereIn('id', $venueIds)->pluck('name')->toArray();
            $venueNames = !empty($names) ? implode(', ', $names) : implode(', ', array_map('strval', $venueIds));
        }

        // Build equipment list with quantities
        $equipment = [];
        if (is_array($r->equipment)) {
            $qtys = $r->equipment_quantities ?? [];
            foreach ($r->equipment as $i => $eq) {
                $qty = $qtys[$i] ?? null;
                $equipment[] = $qty ? "{$eq} (x{$qty})" : $eq;
            }
        }

        return [
            'id'             => $r->id,
            'type'           => 'facility_requests',
            'reference_no'   => $r->reference_no ?? "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->activity ?? $r->purpose ?? '—',
            'sections'       => [
                [
                    'title'  => 'Activity Details',
                    'fields' => [
                        ['label' => 'Activity',     'value' => $r->activity ?? '—',    'full' => true],
                        ['label' => 'Purpose',      'value' => $r->purpose ?? '—',     'full' => true],
                        ['label' => 'Nature',       'value' => $r->nature ?? '—'],
                        ['label' => 'Participants', 'value' => $r->participants ?? '—'],
                        ['label' => 'Male',         'value' => $r->male ?? '—'],
                        ['label' => 'Female',       'value' => $r->female ?? '—'],
                    ],
                ],
                [
                    'title'  => 'Schedule & Venue',
                    'fields' => [
                        ['label' => 'Venue',       'value' => $venueNames,                                                                    'full' => true],
                        ['label' => 'Date Start',  'value' => $r->date_start ?? '—'],
                        ['label' => 'Date End',    'value' => $r->date_end ?? '—'],
                        ['label' => 'Time Start',  'value' => $r->time_start ? substr($r->time_start, 0, 5) : '—'],
                        ['label' => 'Time End',    'value' => $r->time_end   ? substr($r->time_end,   0, 5) : '—'],
                        ['label' => 'Date Filed',  'value' => $r->date_filed ? \Carbon\Carbon::parse($r->date_filed)->format('M d, Y') : ($r->created_at?->format('M d, Y'))],
                        ['label' => 'Status',      'value' => $r->status],
                    ],
                ],
                [
                    'title'  => 'Equipment & Others',
                    'fields' => [
                        ['label' => 'Equipment', 'value' => !empty($equipment) ? implode(', ', $equipment) : '—', 'full' => true],
                        ['label' => 'Others',    'value' => $r->others ?? '—',  'full' => true],
                        ['label' => 'Remarks',   'value' => $r->remarks ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normaliseWorkRequest(WorkRequest $r): array
    {
        return [
            'id'             => $r->id,
            'type'           => 'work_requests',
            'reference_no'   => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->issue ?? '—',
            'sections'       => [
                [
                    'title'  => 'Work Details',
                    'fields' => [
                        ['label' => 'Issue',       'value' => $r->issue ?? '—', 'full' => true],
                        ['label' => 'Category',    'value' => $r->category ?? '—'],
                        ['label' => 'Priority',    'value' => $r->priority ?? '—'],
                        ['label' => 'Description', 'value' => $r->description ?? '—', 'full' => true],
                    ],
                ],
                [
                    'title'  => 'Location & Status',
                    'fields' => [
                        ['label' => 'Building/Division', 'value' => $r->division?->name ?? '—'],
                        ['label' => 'Office/Room',       'value' => $r->office?->name ?? '—'],
                        ['label' => 'Status',            'value' => $r->status],
                        ['label' => 'Filed At',          'value' => $r->created_at?->format('M d, Y h:i A')],
                        ['label' => 'Expected Completion', 'value' => $r->expected_completion_date ?? '—'],
                    ],
                ],
            ],
        ];
    }

    private function normaliseServiceRequest(ServiceRequest $r): array
    {
        return [
            'id'             => $r->id,
            'type'           => 'service_requests',
            'reference_no'   => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->service_type ?? '—',
            'sections'       => [
                [
                    'title'  => 'Service Details',
                    'fields' => [
                        ['label' => 'Service Type',    'value' => $r->service_type ?? '—'],
                        ['label' => 'Date Needed',     'value' => $r->date_needed ?? '—'],
                        ['label' => 'Time Needed',     'value' => $r->time_needed ?? '—'],
                        ['label' => 'Copies',          'value' => $r->copies ?? '—'],
                        ['label' => 'Sheets per Set',  'value' => $r->sheets_per_set ?? '—'],
                        ['label' => 'Status',          'value' => $r->status],
                        ['label' => 'Filed At',        'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title'  => 'Purpose & Details',
                    'fields' => [
                        ['label' => 'Purpose', 'value' => $r->purposes ?? '—', 'full' => true],
                        ['label' => 'Details', 'value' => $r->details ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normaliseMessengerialRequest(MessengerialRequest $r): array
    {
        $deliveryMethods = is_array($r->delivery_methods) ? implode(', ', $r->delivery_methods) : ($r->delivery_methods ?? '—');
        $kinds = is_array($r->messengerial_kinds) ? implode(', ', $r->messengerial_kinds) : ($r->messengerial_kinds ?? '—');

        return [
            'id'             => $r->id,
            'type'           => 'messengerial_requests',
            'reference_no'   => $r->reference_no ?? "#{$r->id}",
            'requester_name' => $r->requestor ?? '—',
            'filed_at'       => $r->created_at?->toISOString(),
            'status'         => $r->status,
            'summary'        => $r->purpose ?? '—',
            'sections'       => [
                [
                    'title'  => 'Request Details',
                    'fields' => [
                        ['label' => 'Reference No.',    'value' => $r->reference_no ?? '—'],
                        ['label' => 'Purpose',          'value' => $r->purpose ?? '—', 'full' => true],
                        ['label' => 'Destination',      'value' => $r->destination ?? '—', 'full' => true],
                        ['label' => 'Delivery Method',  'value' => $deliveryMethods],
                        ['label' => 'Item Kind',        'value' => $kinds],
                        ['label' => 'Status',           'value' => $r->status],
                        ['label' => 'Filed At',         'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title'  => 'Consignee',
                    'fields' => [
                        ['label' => 'Name',    'value' => $r->consignee_name ?? '—'],
                        ['label' => 'Contact', 'value' => $r->consignee_contact ?? '—'],
                        ['label' => 'Email',   'value' => $r->consignee_email ?? '—'],
                    ],
                ],
            ],
        ];
    }


}
