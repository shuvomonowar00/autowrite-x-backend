<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - {{ config('app.name') }}</title>
</head>

<body style="margin: 0; padding: 0; min-width: 100%; background-color: #f3f4f6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
        <!-- Card Container -->
        <div style="background-color: #ffffff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);">
            <!-- Logo -->
            <!-- <div style="text-align: center; margin-bottom: 24px;">
                <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" style="height: 40px;">
            </div> -->

            <!-- Header -->
            <h1 style="color: #1f2937; font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 16px;">
                Verify Your Email Address
            </h1>

            <!-- Content -->
            <div style="color: #4b5563; font-size: 16px; line-height: 24px; margin-bottom: 24px;">
                <p style="margin-bottom: 16px;">Hi {{ $name }},</p>

                <p style="margin-bottom: 16px;">
                    Thanks for getting started with {{ config('app.name') }}! Please verify your email address by clicking the button below.
                </p>
            </div>

            <!-- Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $verificationUrl }}"
                    style="display: inline-block; background-color: #4f46e5; color: #ffffff; padding: 12px 24px; 
                          border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 16px;">
                    Verify Email Address
                </a>
            </div>

            <!-- Fallback URL -->
            <div style="margin: 24px 0; padding: 16px; background-color: #f3f4f6; border-radius: 6px;">
                <p style="margin: 0 0 8px 0; color: #4b5563; font-size: 14px;">
                    If the button doesn't work, copy and paste this URL into your browser:
                </p>
                <p style="margin: 0; word-break: break-all; color: #4f46e5; font-size: 14px;">
                    {{ $verificationUrl }}
                </p>
            </div>

            <!-- Footer -->
            <div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
                <p style="margin-bottom: 16px;">
                    This link will expire in 24 hours. If you did not create an account, no further action is required.
                </p>

                <p style="margin: 0;">
                    Thanks,<br>
                    The {{ config('app.name') }} Team
                </p>
            </div>
        </div>

        <!-- Footer Links -->
        <div style="text-align: center; margin-top: 24px; color: #6b7280; font-size: 12px;">
            <p style="margin-bottom: 8px;">
                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
            </p>
            <p style="margin: 0;">
                <a href="#" style="color: #6b7280; text-decoration: underline; margin: 0 8px;">Privacy Policy</a>
                <a href="#" style="color: #6b7280; text-decoration: underline; margin: 0 8px;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>

</html>