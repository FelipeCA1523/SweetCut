<?php
// Etiquetas y opciones comunes de pedidos (sin boleta ni pasarela de pago)

$ORDER_STATUSES = [
    'nuevo'          => 'Nuevo',
    'confirmado'     => 'Confirmado',
    'en_preparacion' => 'En preparación',
    'listo_retiro'   => 'Listo para retiro',
    'despachado'     => 'Despachado',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];

$PAYMENT_METHODS = [
    'efectivo'       => 'Efectivo',
    'transferencia'  => 'Transferencia bancaria',
    'contra_entrega' => 'Contra entrega',
];

$DELIVERY_TYPES = [
    'pickup'   => 'Retiro en local',
    'delivery' => 'Despacho a domicilio',
];

function orderStatusLabel(string $key): string {
    global $ORDER_STATUSES;
    return $ORDER_STATUSES[$key] ?? ucfirst($key);
}

function paymentLabel(string $key): string {
    global $PAYMENT_METHODS;
    return $PAYMENT_METHODS[$key] ?? ucfirst($key);
}

function deliveryLabel(string $key): string {
    global $DELIVERY_TYPES;
    return $DELIVERY_TYPES[$key] ?? ucfirst($key);
}

// Número de WhatsApp del cliente en formato wa.me (solo dígitos)
function waNumber(string $phone): string {
    return preg_replace('/[^0-9]/', '', $phone);
}

// Enlace para que la tienda hable con el cliente desde el admin
function waContactLink(string $phone, string $orderNo): string {
    $num = waNumber($phone);
    if ($num === '') {
        return '';
    }
    $text = 'Hola, te escribimos por tu pedido ' . $orderNo . ' en SweetCut.';
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}