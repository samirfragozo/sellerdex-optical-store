<?php

namespace App\Http\Controllers\LensOrder;

use App\Enums\LensOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\LensOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NotifyCustomerController extends Controller
{
    /** Records that the customer was told the glasses are ready, then hands over to WhatsApp. */
    public function __invoke(LensOrder $lensOrder): RedirectResponse
    {
        Gate::authorize('update', $lensOrder);
        abort_unless($lensOrder->lab_status === LensOrderStatus::Ready, 409);

        $url = $lensOrder->customerNoticeUrl();
        abort_if($url === null, 404);

        $lensOrder->update(['customer_notified_at' => now()]);

        return redirect()->away($url);
    }
}
