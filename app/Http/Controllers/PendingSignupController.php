<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerSignupRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only review queue for mobile self-signups — see rule 5.34. Route is
 * fully inside a role:admin group (routes/web.php), so no inline role guard
 * here, same trust-the-middleware precedent as AnnouncementController.
 */
class PendingSignupController extends Controller
{
    public function index()
    {
        $pending = CustomerSignupRequest::where('status', 'pending')->latest()->get();

        $history = CustomerSignupRequest::whereIn('status', ['approved', 'rejected'])
            ->with('reviewedBy', 'customer')
            ->latest('reviewed_at')
            ->paginate(20);

        return view('admin.pending-signups.index', compact('pending', 'history'));
    }

    public function approve(CustomerSignupRequest $signup)
    {
        abort_unless($signup->status === 'pending', 404);

        $customer = DB::transaction(function () use ($signup) {
            $customer = Customer::create([
                'name'           => $signup->name,
                'contact_number' => $signup->contact_number,
                'city'           => $signup->city,
                'country'        => $signup->country,
                'address'        => $signup->address,
                'email'          => $signup->email,
                // The approving admin is the staff member responsible for this
                // customer existing — reuses the existing non-nullable FK, no
                // schema change needed.
                'created_by'     => Auth::id(),
            ]);

            activity()
                ->performedOn($customer)
                ->causedBy(Auth::user())
                ->event('detail')
                ->withProperties([
                    'name'           => $customer->name,
                    'email'          => $customer->email,
                    'contact_number' => $customer->contact_number,
                    'city'           => $customer->city,
                    'country'        => $customer->country,
                    'address'        => $customer->address,
                ])
                ->log('Customer "' . $customer->name . '" approved from mobile signup request');

            $signup->update([
                'status'      => 'approved',
                'customer_id' => $customer->id,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);

            return $customer;
        });

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Signup approved — share the portal link below.');
    }

    public function reject(CustomerSignupRequest $signup)
    {
        abort_unless($signup->status === 'pending', 404);

        $signup->update([
            'status'      => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Signup rejected.');
    }
}
