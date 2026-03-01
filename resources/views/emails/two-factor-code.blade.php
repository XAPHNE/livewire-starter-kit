<x-mail::message>
# Two Factor Authentication Code

Your login verification code is:

<x-mail::panel>
# {{ $code }}
</x-mail::panel>

This code will expire in 10 minutes.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
