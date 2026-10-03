<?php

namespace App\Support;

use App\Models\LensOrder;
use App\Models\Prescription;

/** The lab order as plain text, for WhatsApp and e-mail — so the lab retypes nothing. */
class LabOrderMessage
{
    public static function for(LensOrder $order): string
    {
        $order->loadMissing(['saleItem.sale', 'saleItem.lensConfig.patient', 'supplier']);
        $rx = $order->prescription_snapshot ?? [];
        $eye = fn (string $side): string => __('app.lab_order.message.eye', [
            'eye' => strtoupper($side),
            'sphere' => Prescription::formatDiopter($rx["{$side}_sphere"] ?? null) ?: '—',
            'cylinder' => Prescription::formatDiopter($rx["{$side}_cylinder"] ?? null) ?: '—',
            'axis' => $rx["{$side}_axis"] ?? '—',
            'add' => Prescription::formatDiopter($rx["{$side}_add"] ?? null) ?: '—',
            'prism' => filled($rx["{$side}_prism"] ?? null) ? $rx["{$side}_prism"].' '.($rx["{$side}_prism_base"] ?? '') : '—',
        ]);

        $lines = [
            self::title($order),
            __('app.lab_order.message.patient', ['name' => $order->saleItem?->lensConfig?->patient?->full_name ?? '—']),
            $eye('od'),
            $eye('os'),
            __('app.lab_order.message.pd', ['od' => $order->od_pd ?? '—', 'os' => $order->os_pd ?? '—']),
            __('app.lab_order.message.heights', ['od' => $order->od_height ?? '—', 'os' => $order->os_height ?? '—']),
            __('app.lab_order.message.lens', ['lens' => $order->saleItem?->description ?? '—']),
            __('app.lab_order.message.frame', [
                'type' => $order->frame_type?->label() ?? '—',
                'a' => $order->frame_a ?? '—', 'b' => $order->frame_b ?? '—', 'dbl' => $order->frame_dbl ?? '—',
                'source' => $order->frame_source?->label() ?? '—',
                'description' => $order->frameDescription() ?? '—',
            ]),
        ];

        if (filled($order->customer_frame_condition)) {
            $lines[] = __('app.lab_order.message.frame_condition', ['condition' => $order->customer_frame_condition]);
        }

        if (filled($order->notes)) {
            $lines[] = __('app.lab_order.message.notes', ['notes' => $order->notes]);
        }

        return implode("\n", $lines);
    }

    /** A mailto: link to the lab with the order prefilled, or null when the lab has no e-mail. */
    public static function mailtoUrl(LensOrder $order): ?string
    {
        $email = $order->supplier?->email;

        if (blank($email)) {
            return null;
        }

        return 'mailto:'.$email.'?subject='.rawurlencode(self::title($order)).'&body='.rawurlencode(self::for($order));
    }

    private static function title(LensOrder $order): string
    {
        $order->loadMissing('saleItem.sale');

        return __('app.lab_order.message.title', ['number' => $order->id, 'sale' => $order->saleItem?->sale?->number]);
    }
}
