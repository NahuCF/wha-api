<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.verify_account.subject', [], $locale) }}</title>
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
            padding: 15px 60px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .content {
            margin: 30px 0;
        }
        .welcome {
            font-size: 24px;
            font-weight: 600;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .message {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
        }
        .due-date {
            font-size: 14px;
            color: #666;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .signature {
            font-size: 14px;
            color: #555;
            margin-top: 30px;
            line-height: 1.5;
        }
        .divider {
            height: 1px;
            background-color: #e5e5e5;
            margin: 20px 0;
        }
        .button-primary {
            display: inline-block;
            padding: 10px 30px;
            background-color: #007BFF;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .button-primary:hover {
            background-color: #0056b3;
        }
        .unsubscribe {
            display: inline-block;
            color: #888;
            text-decoration: underline;
            font-size: 12px;
        }
        .unsubscribe:hover {
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <div class="greeting">
                {{ __('emails.verify_account.greeting', ['name' => $name], $locale) }}
            </div>
            
            <div class="welcome">
                Welcome to {{ config('app.name', 'WHA-API') }}!
            </div>
            
            <p class="message">
                {{ __('emails.verify_account.line1', ['app_name' => config('app.name', 'WHA-API')], $locale) }}
            </p>
            
            <a href="{{ $link }}" class="button-primary">
                {{ __('emails.verify_account.action', [], $locale) }}
            </a>
            
            <p class="due-date">
                This invitation will expire in 24 hours.
            </p>
            
            <div class="signature">
                Thanks,<br>
                The {{ config('app.name', 'WHA-API') }} Team
            </div>
            
            <div class="divider"></div>
            
            <div>
                <a href="#" class="unsubscribe" onclick="return false;">
                    Click here to unsubscribe
                </a>
            </div>
        </div>
    </div>
</body>
</html>