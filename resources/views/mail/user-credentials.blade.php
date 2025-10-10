<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.user_credentials.subject', ['app_name' => config('app.name', 'WHA-API')], $locale) }}</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <p>
                {{ __('emails.user_credentials.invited_to', [], $locale) }} <strong>{{ $companyName }}</strong>
            </p>
            
            <a href="{{ config('app.client_url') }}/accept-invite?email={{ urlencode($email) }}" class="button-primary">
                {{ __('emails.user_credentials.accept_invite', [], $locale) }}
            </a>
            
            <div style="margin-top: 30px;">
                <div class="credential-item">
                    <div class="credential-label">{{ __('emails.user_credentials.email_label', [], $locale) }}</div>
                    <div class="credential-value">{{ $email }}</div>
                </div>
                
                <div class="credential-item">
                    <div class="credential-label">{{ __('emails.user_credentials.password_label', [], $locale) }}</div>
                    <div class="credential-value">{{ $password }}</div>
                </div>

                <div class="credential-item" style="margin-top: 1.5rem">
                    <div>{{ __('emails.user_credentials.login_text', [], $locale) }}</div>
                    <div>
                        <a target="_blank" href="{{ $link }}">{{ __('emails.user_credentials.login_link', [], $locale) }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>