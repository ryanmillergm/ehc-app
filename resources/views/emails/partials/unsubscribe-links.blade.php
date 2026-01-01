@props([
    'unsubscribeAllUrl',
    'unsubscribeThisUrl' => null,
    'managePreferencesUrl' => null,
])

<p style="margin:24px 0 0; font-size:12px; line-height:1.5; color:#6b7280;">
    @if ($unsubscribeThisUrl)
        <a href="{{ $unsubscribeThisUrl }}" style="color:#6b7280; text-decoration:underline;">
            Unsubscribe from this list
        </a>
        <span style="margin:0 8px;">•</span>
    @endif

    <a href="{{ $unsubscribeAllUrl }}" style="color:#6b7280; text-decoration:underline;">
        Unsubscribe from all marketing
    </a>

    @if ($managePreferencesUrl)
        <span style="margin:0 8px;">•</span>
        <a href="{{ $managePreferencesUrl }}" style="color:#6b7280; text-decoration:underline;">
            Manage email preferences
        </a>
    @endif
</p>
