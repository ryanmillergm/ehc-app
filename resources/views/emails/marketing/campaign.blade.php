<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body style="margin:0; padding:0; background:#f9fafb;">
        <div style="max-width:640px; margin:0 auto; padding:24px; font-family:Arial, sans-serif; color:#111827;">
            <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:24px;">
                <div style="font-size:12px; color:#6b7280; margin-bottom:10px;">
                    {{ $list->label }}
                </div>

                @if($subscriber->name)
                    <p style="margin:0 0 14px; color:#374151;">
                        Hi {{ $subscriber->name }},
                    </p>
                @endif

                <div style="font-size:15px; line-height:1.6;">
                    {!! $bodyHtml !!}
                </div>

                @include('emails.partials.unsubscribe-links', [
                    'unsubscribeAllUrl' => $unsubscribeAllUrl,
                    'unsubscribeThisUrl' => $unsubscribeThisUrl,
                    'managePreferencesUrl' => $managePreferencesUrl,
                ])
            </div>

            <p style="margin:16px 0 0; font-size:12px; color:#9ca3af; text-align:center;">
                © {{ date('Y') }} {{ config('app.name') }}
            </p>
        </div>
    </body>
</html>
