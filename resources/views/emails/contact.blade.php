@component('mail::message')
# New Contact Form Submission

**Name:** {{ $name }}  
**Email:** {{ $email }}  
**Message:**  
{{ $message }}

@component('mail::button', ['url' => 'mailto:' . $email])
Reply to Sender
@endcomponent

Thanks,  
{{ config('app.name') }} Team
@endcomponent