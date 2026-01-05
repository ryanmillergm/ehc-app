@props([
    'unsubscribeAllUrl',
    'unsubscribeThisUrl' => null,
    'managePreferencesUrl' => null,
])

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
    <tr>
        <td style="font-family:Arial,sans-serif;font-size:12px;line-height:1.5;color:#6b7280;">
            {{-- Use spans + non-breaking spaces for consistent separators --}}
            @if ($unsubscribeThisUrl)
                <a href="{{ $unsubscribeThisUrl }}" style="color:#6b7280;text-decoration:underline;">
                    Unsubscribe from this list
                </a>
                <span style="white-space:nowrap;">&nbsp;&bull;&nbsp;</span>
            @endif

            <a href="{{ $unsubscribeAllUrl }}" style="color:#6b7280;text-decoration:underline;">
                Unsubscribe from all marketing
            </a>

            @if ($managePreferencesUrl)
                <span style="white-space:nowrap;">&nbsp;&bull;&nbsp;</span>
                <a href="{{ $managePreferencesUrl }}" style="color:#6b7280;text-decoration:underline;">
                    Manage email preferences
                </a>
            @endif
        </td>
    </tr>
</table>
