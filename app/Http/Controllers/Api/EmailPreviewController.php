<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SendOTPCode;
use App\Mail\SendVerifyAccount;
use Illuminate\Http\Request;

class EmailPreviewController extends Controller
{
    public function previewVerifyAccount(Request $request)
    {
        $input = $request->validate([
            'link' => ['required', 'string', 'url'],
        ]);

        $mailable = new SendVerifyAccount($input['link']);

        return $this->renderMailable($mailable);
    }

    public function previewOTP(Request $request)
    {
        $input = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $mailable = new SendOTPCode($input['code']);

        return $this->renderMailable($mailable);
    }

    private function renderMailable($mailable)
    {
        // Render the email to HTML
        $html = $mailable->render();

        return response()->json([
            'subject' => $mailable->envelope()->subject,
            'html' => $html,
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ]);
    }

    public function previewInBrowser(Request $request, string $type)
    {
        $input = $request->validate([
            'link' => ['sometimes', 'string', 'url'],
            'code' => ['sometimes', 'string'],
            'name' => ['sometimes', 'string'],
            'locale' => ['sometimes', 'string', 'in:en,es'],
        ]);

        $locale = $input['locale'] ?? 'en';
        $name = $input['name'] ?? 'John Doe';

        $mailable = match ($type) {
            'verify-account' => new SendVerifyAccount(
                $input['link'] ?? 'https://example.com/verify?token=sample-token',
                $name,
                $locale
            ),
            'otp' => new SendOTPCode($input['code'] ?? '123456'),
            default => abort(404, 'Email type not found')
        };

        // Return raw HTML for browser preview
        return $mailable->render();
    }
}
