<?php

namespace App\Http\Controllers;

use App\Models\Event;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

class EventAccessQrCodeController extends Controller
{
    public function __invoke(Event $event): Response
    {
        abort_unless(auth()->user()?->canAccessPanel(filament()->getPanel('admin')), 403);

        $url = route('event-access-codes.show', $event->access_code);
        $renderer = new ImageRenderer(
            new RendererStyle(320),
            new SvgImageBackEnd()
        );

        return response((new Writer($renderer))->writeString($url), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$event->slug.'-access-qr.svg"',
        ]);
    }
}
