@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => config('app.url')])
            {{ config('app.name') }}
        @endcomponent
    @endslot

    # Actualización de pedido #{{ $order->id }}

    Hola {{ $user->first_name }},

    El estado de tu pedido ha cambiado a: **{{ $order->status }}**

    **Detalles del pedido:**
    - Total: {{ $order->total }}
    - Método de pago: {{ $order->payment_method }}
    - Fecha: {{ $order->created_at->format('d/m/Y H:i') }}

    @component('mail::button', ['url' => route('orders.show', $order->id)])
        Ver Pedido
    @endcomponent

    @slot('footer')
        @component('mail::footer')
            ¿Preguntas? Contacta con nuestro soporte: soporte@tudominio.com
        @endcomponent
    @endslot
@endcomponent
