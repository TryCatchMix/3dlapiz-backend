@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            {{ config('app.name') }}
        @endcomponent
    @endslot

    # Verificación de correo electrónico

    Hola {{ $user->first_name }},

    Por favor haz clic en el botón para verificar tu dirección de email:

    @component('mail::button', ['url' => $verificationUrl])
        Verificar Email
    @endcomponent

    Si no creaste una cuenta, puedes ignorar este mensaje.

    @slot('footer')
        @component('mail::footer')
            © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        @endcomponent
    @endslot
@endcomponent
