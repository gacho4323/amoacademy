@component('mail::message')
# Course Extension Confirmation

Hello {{ $notifiable->name }},

Your enrollment in the course **{{ $course->title }}** has been successfully extended.

## Extension Details
- **New Expiry Date**: {{ $newExpiryDate }}
- **Extension Type**: {{ $cost > 0 ? '6 months with a 30% discount' : '1 month for free' }}
- **Cost**: {{ $cost > 0 ? $cost . ' USD' : 'Free' }}

@component('mail::button', ['url' => url("/courses/{$course->id}")])
View Course
@endcomponent

Thank you for continuing your learning journey with us!

Best regards,  
{{ config('app.name') }} Team
@endcomponent