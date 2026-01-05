<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ $subjectLine ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f9fafb;">
@php
    // Hidden preview text (preheader). Keep it plain text.
    $preheader = trim(strip_tags($bodyHtml ?? ''));
    $preheader = preg_replace('/\s+/', ' ', $preheader);
    $preheader = \Illuminate\Support\Str::limit($preheader, 140, '');
@endphp

<!-- Preheader (hidden) -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
    {{ $preheader }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb;">
    <tr>
        <td align="center" style="padding:24px 12px;">

            <!-- Container -->
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" style="width:640px;max-width:640px;">
                <tr>
                    <td style="padding:0 0 12px 0;font-family:Arial,sans-serif;color:#6b7280;font-size:12px;">
                        {{ $list->label }}
                    </td>
                </tr>

                <!-- Card -->
                <tr>
                    <td style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="padding:24px;font-family:Arial,sans-serif;color:#111827;">

                                    @if($subscriber->name)
                                        <p style="margin:0 0 14px 0;color:#374151;font-size:14px;">
                                            Hi {{ $subscriber->name }},
                                        </p>
                                    @endif

                                    <div style="font-size:15px;line-height:1.6;color:#111827;">
                                        {!! $bodyHtml !!}
                                    </div>

                                    <div style="margin-top:20px;">
                                        @include('emails.partials.unsubscribe-links', [
                                            'unsubscribeAllUrl' => $unsubscribeAllUrl,
                                            'unsubscribeThisUrl' => $unsubscribeThisUrl,
                                            'managePreferencesUrl' => $managePreferencesUrl,
                                        ])
                                    </div>

                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td align="center" style="padding:16px 8px 0 8px;font-family:Arial,sans-serif;color:#9ca3af;font-size:12px;">
                        © {{ date('Y') }} {{ config('app.name') }}
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
</body>
</html>
