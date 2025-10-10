<!DOCTYPE html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.password_reset.subject', [], $locale) }}</title>
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
        .greeting {
            font-size: 17px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
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
        .warning {
            font-size: 14px;
            color: #666;
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 3px solid #ffc107;
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
            <div class="greeting">
                {{ __('emails.password_reset.greeting', ['name' => $name], $locale) }}
            </div>
            
            <p class="message">
                {{ __('emails.password_reset.line1', [], $locale) }}
            </p>
            
            <a href="{{ $resetLink }}" class="button-primary">
                {{ __('emails.password_reset.action', [], $locale) }}
            </a>
        
            <div class="footer">
                {{ __('emails.password_reset.expire', [], $locale) }}
            </div>
        </div>
    </div>
</body>
</html>