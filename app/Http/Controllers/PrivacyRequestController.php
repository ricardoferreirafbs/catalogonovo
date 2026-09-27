<?php

namespace App\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Notifications\VerifyPrivacyRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PrivacyRequestController extends Controller
{
    public function index(): View
    {
        return view('privacy.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'request_type' => ['required', Rule::in(array_keys(PrivacyRequest::TYPE_LABELS))],
            'scope' => ['required', Rule::in(['platform', 'tenant'])],
            'company_reference' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:4000'],
            'privacy_acknowledgement' => ['accepted'],
        ], [
            'privacy_acknowledgement.accepted' => 'Confirme que os dados serão usados para validar e atender esta solicitação.',
        ]);

        $privacyRequest = PrivacyRequest::create([
            'protocol' => 'LGPD-'.Str::upper(Str::random(10)),
            'request_type' => $data['request_type'],
            'scope' => $data['scope'],
            'requester_name' => $data['name'],
            'requester_email' => $data['email'],
            'requester_email_hash' => hash_hmac('sha256', $data['email'], (string) config('app.key')),
            'company_reference' => $data['company_reference'] ?? null,
            'details' => $data['details'] ?? null,
            'status' => 'awaiting_verification',
            'retention_until' => now()->addDays(max(7, (int) config('privacy.unverified_retention_days', 30))),
        ]);

        try {
            $privacyRequest->notify(new VerifyPrivacyRequestNotification($privacyRequest));
        } catch (Throwable $exception) {
            Log::error('Não foi possível enviar a confirmação da solicitação de privacidade.', [
                'protocol' => $privacyRequest->protocol,
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('privacy.index')->with(
            'success',
            'Solicitação recebida. Protocolo '.$privacyRequest->protocol.'. Confira seu e-mail para confirmar o pedido.'
        );
    }

    public function verify(PrivacyRequest $privacyRequest): RedirectResponse
    {
        if (! $privacyRequest->email_verified_at) {
            $privacyRequest->update([
                'email_verified_at' => now(),
                'status' => 'verified',
                'retention_until' => now()->addDays(max(90, (int) config('privacy.request_retention_days', 730))),
            ]);
        }

        $trackingUrl = URL::temporarySignedRoute(
            'privacy.requests.track',
            now()->addHours(max(1, (int) config('privacy.tracking_link_hours', 168))),
            ['privacyRequest' => $privacyRequest->protocol],
        );

        return redirect($trackingUrl)->with(
            'success',
            'E-mail confirmado. A solicitação '.$privacyRequest->protocol.' foi encaminhada para análise.'
        );
    }

    public function track(PrivacyRequest $privacyRequest): View
    {
        abort_unless($privacyRequest->email_verified_at, 404);

        return view('privacy.track', compact('privacyRequest'));
    }
}
