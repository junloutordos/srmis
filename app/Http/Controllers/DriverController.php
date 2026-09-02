<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class DriverController extends Controller
{
    /**
     * Return a list of drivers (users whose position contains 'Driver')
     */
    public function index()
    {
        $drivers = User::where('position', 'LIKE', '%Driver%')->orderBy('name')->get(['id', 'name', 'position']);
        return response()->json($drivers);
    }

    /**
     * GSU dispatch: assign a driver AND a vehicle to a vehicle request that is
     * awaiting GSU assignment. Transitions the request to
     * "Pending Division Chief Approval" and notifies the (auto-resolved)
     * Division Chief so they can review and sign off.
     */
    public function assign(Request $request, VehicleRequest $vehicleRequest)
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'vehicle_id' => 'required|exists:vehicles,id',
        ]);

        if ($vehicleRequest->status !== 'Pending GSU Assignment') {
            return response()->json([
                'message' => 'This request is not awaiting GSU assignment.',
            ], 422);
        }

        // Build dates array from request
        $dates = [];
        if (is_array($vehicleRequest->date_needed_multiple) && count($vehicleRequest->date_needed_multiple) > 0) {
            $dates = $vehicleRequest->date_needed_multiple;
        } elseif (! empty($vehicleRequest->date_needed)) {
            $dates = [
                ($vehicleRequest->date_needed instanceof Carbon) ? $vehicleRequest->date_needed->format('Y-m-d') : date('Y-m-d', strtotime($vehicleRequest->date_needed))
            ];
        }

        $timeStart = $vehicleRequest->time_of_departure;
        $timeEnd = $vehicleRequest->eta;

        // Normalise to H:i once — these come straight from the DB's TIME
        // columns (H:i:s), but the request/existing-booking comparisons below
        // work in H:i. Guards against both 'H:i' and 'H:i:s' inputs so it
        // doesn't blow up if the value is ever stored without seconds.
        $normaliseTime = function (?string $time): ?string {
            if (empty($time)) return null;
            foreach (['H:i:s', 'H:i'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $time)->format('H:i');
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return substr($time, 0, 5);
        };

        $timeStart = $normaliseTime($timeStart);
        $timeEnd = $normaliseTime($timeEnd);

        // Determine vehicle name to check conflicts against
        $vehicleName = $vehicleRequest->vehicle_type;
        if ($request->filled('vehicle_id')) {
            $v = \App\Models\Vehicle::find($request->input('vehicle_id'));
            if ($v) $vehicleName = $v->name;
        }

        // Vehicle availability check
        $vehicleConflicts = [];
        if ($vehicleName && count($dates) > 0) {
            foreach ($dates as $d) {
                $existing = VehicleRequest::where('vehicle_type', $vehicleName)
                    ->where('id', '!=', $vehicleRequest->id)
                    ->whereNotIn('status', ['Declined', 'Pending GSU Assignment'])
                    ->where(function ($q) use ($d) {
                        $q->whereDate('date_needed', $d)
                          ->orWhereJsonContains('date_needed_multiple', $d);
                    })
                    ->get();

                foreach ($existing as $ex) {
                    if (empty($ex->time_of_departure) || empty($ex->eta) || empty($timeStart) || empty($timeEnd)) {
                        $vehicleConflicts[] = $d;
                        break;
                    }
                    $exStart = $normaliseTime($ex->time_of_departure);
                    $exEnd = $normaliseTime($ex->eta);
                    $nStart = $timeStart;
                    $nEnd = $timeEnd;

                    if ($nStart < $exEnd && $nEnd > $exStart) {
                        $vehicleConflicts[] = $d;
                        break;
                    }
                }
            }
        }

        if (! empty($vehicleConflicts)) {
            $unique = array_values(array_unique($vehicleConflicts));
            return response()->json([
                'type' => 'vehicle',
                'message' => "The vehicle {$vehicleName} is already booked on the following date(s): " . implode(', ', $unique),
                'dates' => $unique,
            ], 422);
        }

        // Driver availability check
        $driverId = $request->input('driver_id');
        $driverConflicts = [];
        if ($driverId && count($dates) > 0) {
            foreach ($dates as $d) {
                $existing = VehicleRequest::where('driver_id', $driverId)
                    ->where('id', '!=', $vehicleRequest->id)
                    ->whereNotIn('status', ['Declined', 'Pending GSU Assignment'])
                    ->where(function ($q) use ($d) {
                        $q->whereDate('date_needed', $d)
                          ->orWhereJsonContains('date_needed_multiple', $d);
                    })
                    ->get();

                foreach ($existing as $ex) {
                    if (empty($ex->time_of_departure) || empty($ex->eta) || empty($timeStart) || empty($timeEnd)) {
                        $driverConflicts[] = $d;
                        break;
                    }
                    $exStart = $normaliseTime($ex->time_of_departure);
                    $exEnd = $normaliseTime($ex->eta);
                    $nStart = $timeStart;
                    $nEnd = $timeEnd;

                    if ($nStart < $exEnd && $nEnd > $exStart) {
                        $driverConflicts[] = $d;
                        break;
                    }
                }
            }
        }

        if (! empty($driverConflicts)) {
            $unique = array_values(array_unique($driverConflicts));
            return response()->json([
                'type' => 'driver',
                'message' => "The selected driver is already booked on the following date(s): " . implode(', ', $unique),
                'dates' => $unique,
            ], 422);
        }

        // Persist assignment, required vehicle, and transition to DC approval
        $vehicle = \App\Models\Vehicle::find($request->input('vehicle_id'));

        $vehicleRequest->driver_id = $driverId;
        $vehicleRequest->vehicle_type = $vehicle->name;
        $vehicleRequest->status = 'Pending Division Chief Approval';
        $vehicleRequest->save();

        // Notify the (auto-resolved) Division Chief for review and approval
        if ($vehicleRequest->division_chief_id) {
            $chief = User::find($vehicleRequest->division_chief_id);
            if ($chief) {
                if ($chief->email) {
                    try {
                        $approveUrl = URL::signedRoute('vehicle-requests.approve', ['vehicleRequest' => $vehicleRequest->id, 'chief' => $chief->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('vehicle-requests.decline', ['vehicleRequest' => $vehicleRequest->id, 'chief' => $chief->id], now()->addDays(7));
                        Mail::to($chief->email)->send(new \App\Mail\VehicleRequestCreatedMail($vehicleRequest, $approveUrl, $declineUrl));
                    } catch (\Throwable $e) {
                        \Log::error('Failed to send Division Chief notification after GSU dispatch', ['error' => $e->getMessage()]);
                    }
                }
                \App\Services\NotificationService::notifyUser($chief, 'Vehicle Request', "#{$vehicleRequest->id}", 'Driver and vehicle assigned — awaiting your approval', route('vehicle-requests.dc-approval'));
            }
        }

        // Notify the requester that their trip has been dispatched
        if ($vehicleRequest->requester) {
            \App\Services\NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Driver and vehicle assigned by GSU', route('vehicle-requests.index'));
        }

        return response()->json(['success' => true]);
    }
}
