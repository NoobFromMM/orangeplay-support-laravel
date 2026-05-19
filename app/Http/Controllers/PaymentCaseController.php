<?php

namespace App\Http\Controllers;

use App\Models\PaymentCase;
use App\Services\Payments\PaymentCaseResolutionService;
use Illuminate\Http\RedirectResponse;

class PaymentCaseController extends Controller
{
    public function approve(
        PaymentCase $paymentCase,
        PaymentCaseResolutionService $resolutionService,
    ): RedirectResponse {
        try {
            $resolutionService->approve($paymentCase, [
                'reviewer_name' => 'Dashboard',
            ]);

            return back()->with('success', 'Payment case approved.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'Cannot approve: ' . $e->getMessage());
        }
    }

    public function reject(
        PaymentCase $paymentCase,
        PaymentCaseResolutionService $resolutionService,
    ): RedirectResponse {
        try {
            $resolutionService->reject($paymentCase, [
                'reviewer_name' => 'Dashboard',
            ]);

            return back()->with('success', 'Payment case rejected.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'Cannot reject: ' . $e->getMessage());
        }
    }
}
