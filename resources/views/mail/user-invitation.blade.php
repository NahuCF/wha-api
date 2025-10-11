<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.user_invitation.subject', ['app_name' => config('app.name', 'WHA-API')], $locale) }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            max-width: 500px;
            margin: 0 auto;
            padding: 10px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .content {
            margin: 20px 0;
        }
        .button-primary {
            display: inline-block;
            padding: 12px 35px;
            background-color: #007BFF;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 20px auto;
            display: block;
            width: fit-content;
        }
        .button-primary:hover {
            background-color: #0056b3;
        }
        .credential-item {
            font-size: 15px;
            display: flex;
            gap: .5rem;
            margin: 10px 0;
        }
        .credential-label {
            color: #666;
        }
        .credential-value {
            font-weight: bold;
            color: #333;
        }

         .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e5e5;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <p>
                {{ __('emails.user_invitation.greeting', ['name' => $userName], $locale) }}
            </p>
            
            <p>
                {{ __('emails.user_invitation.invited_to', [], $locale) }} <strong>{{ $companyName }}</strong>
            </p>
            
            <p>
                {{ __('emails.user_invitation.set_password_text', [], $locale) }}
            </p>
            
            <a href="{{ $setPasswordLink }}" class="button-primary">
                {{ __('emails.user_invitation.accept_button', [], $locale) }}
            </a>
            
            <div class="footer">
                <p>
                    {{ __('emails.user_invitation.support_note', [], $locale) }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>