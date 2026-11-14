<?php

use App\Support\WhatsApp;

it('builds a wa.me link with the normalized phone and encoded Arabic text', function () {
    $link = WhatsApp::link('0501234567', 'مرفق عرض السعر رقم QT-2026-0001');

    expect($link)->toStartWith('https://wa.me/971501234567?text=')
        ->and(rawurldecode(substr($link, strlen('https://wa.me/971501234567?text='))))->toBe('مرفق عرض السعر رقم QT-2026-0001');
});

it('builds a link without a phone', fn () => expect(WhatsApp::link(null, 'x'))->toBe('https://wa.me/?text=x'));
