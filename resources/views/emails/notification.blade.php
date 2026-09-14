<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preview ?? $intro }}</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f7fb;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px;">
                    <tr>
                        <td style="padding:0 4px 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:12px;">
                                        <img src="{{ $brandLogo ?? asset('img/jgu.png') }}" width="72" alt="{{ $brandName ?? 'Jakarta Global University' }}" style="display:block;width:72px;height:auto;border:0;">
                                    </td>
                                    <td style="vertical-align:middle;border-left:1px solid #dbe2ea;padding-left:12px;">
                                        <div style="font-size:17px;font-weight:700;line-height:22px;color:#111827;">{{ $brandName ?? 'MSC Hub' }}</div>
                                        <div style="font-size:11px;line-height:16px;color:#6b7280;letter-spacing:.04em;">{{ $brandTagline ?? 'MEDIA & STRATEGIC COMMUNICATIONS' }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.06);">
                            <div style="height:5px;background:#2563eb;font-size:0;line-height:0;">&nbsp;</div>
                            <div style="padding:36px 40px 32px;">
                                <div style="display:inline-block;margin-bottom:16px;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">
                                    {{ $badge ?? 'Pemberitahuan MSC Hub' }}
                                </div>
                                <h1 style="margin:0 0 12px;font-size:26px;line-height:34px;color:#0f172a;font-weight:700;">{{ $title }}</h1>
                                <p style="margin:0 0 8px;font-size:16px;line-height:26px;color:#334155;">{{ $greeting }}</p>
                                <p style="margin:0 0 26px;font-size:15px;line-height:25px;color:#64748b;">{{ $intro }}</p>

                                @isset($status)
                                    @php
                                        $statusStyles = match($statusTone ?? 'info') {
                                            'success' => ['background' => '#ecfdf5', 'border' => '#a7f3d0', 'color' => '#047857'],
                                            'warning' => ['background' => '#fffbeb', 'border' => '#fde68a', 'color' => '#b45309'],
                                            'danger' => ['background' => '#fef2f2', 'border' => '#fecaca', 'color' => '#b91c1c'],
                                            default => ['background' => '#eff6ff', 'border' => '#bfdbfe', 'color' => '#1d4ed8'],
                                        };
                                    @endphp
                                    <div style="margin:0 0 22px;padding:14px 16px;border:1px solid {{ $statusStyles['border'] }};border-radius:10px;background:{{ $statusStyles['background'] }};color:{{ $statusStyles['color'] }};">
                                        <div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;opacity:.8;">Status terbaru</div>
                                        <div style="margin-top:4px;font-size:16px;font-weight:700;">{{ $status }}</div>
                                    </div>
                                @endisset

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;overflow:hidden;">
                                    @foreach($details as $label => $value)
                                        <tr>
                                            <td style="width:38%;padding:11px 14px;border-bottom:{{ $loop->last ? '0' : '1px solid #eef2f7' }};background:#f8fafc;color:#64748b;font-size:13px;line-height:20px;vertical-align:top;">{{ $label }}</td>
                                            <td style="padding:11px 14px;border-bottom:{{ $loop->last ? '0' : '1px solid #eef2f7' }};color:#1e293b;font-size:13px;font-weight:600;line-height:20px;vertical-align:top;">{{ $value ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </table>

                                @if(!empty($note))
                                    <div style="margin:0 0 24px;padding:14px 16px;border-left:3px solid {{ ($statusTone ?? null) === 'danger' ? '#dc2626' : '#2563eb' }};background:#f8fafc;color:#475569;font-size:14px;line-height:22px;">
                                        {{ $note }}
                                    </div>
                                @endif

                                <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                    <tr>
                                        <td style="border-radius:9px;background:#2563eb;">
                                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 20px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;line-height:18px;">{{ $actionText }}</a>
                                        </td>
                                        @isset($secondaryActionUrl)
                                            <td style="width:10px;">&nbsp;</td>
                                            <td style="border:1px solid #cbd5e1;border-radius:9px;background:#ffffff;">
                                                <a href="{{ $secondaryActionUrl }}" style="display:inline-block;padding:12px 19px;color:#1d4ed8;text-decoration:none;font-size:14px;font-weight:700;line-height:18px;">{{ $secondaryActionText ?? 'Lihat detail' }}</a>
                                            </td>
                                        @endisset
                                    </tr>
                                </table>

                                <p style="margin:26px 0 0;font-size:12px;line-height:19px;color:#94a3b8;">Jika tombol tidak dapat dibuka, salin tautan berikut ke browser:<br><a href="{{ $actionUrl }}" style="color:#64748b;word-break:break-all;">{{ $actionUrl }}</a></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 12px 0;text-align:center;color:#94a3b8;font-size:11px;line-height:18px;">
                            Email ini dikirim otomatis oleh MSC Hub JGU. Mohon tidak membalas email ini.<br>
                            &copy; {{ date('Y') }} {{ $brandFooter ?? 'Jakarta Global University' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
