<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\ImageVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageVariantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_thumbnail_variant_is_generated_at_configured_size(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        Storage::fake('local');

        $event = Event::create([
            'name' => 'Variant Event',
            'slug' => 'variant-event',
        ]);

        $photo = EventPhoto::create([
            'event_id' => $event->id,
            'original_filename' => 'runner.jpg',
            'storage_disk' => 'local',
            'storage_path' => 'event-photos/runner.jpg',
            'mime_type' => 'image/jpeg',
            'status' => 'indexed',
        ]);

        Storage::disk('local')->put($photo->storage_path, $this->jpegImage(900, 600));

        $variants = app(ImageVariantService::class)->generate($photo);

        $this->assertSame(256, $variants['thumbnail']['width']);
        $this->assertSame(256, $variants['thumbnail']['height']);
        $this->assertTrue(Storage::disk('local')->exists($variants['thumbnail']['path']));

        [$width, $height] = getimagesize(Storage::disk('local')->path($variants['thumbnail']['path']));

        $this->assertSame(256, $width);
        $this->assertSame(256, $height);
    }

    private function jpegImage(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate($image, 42, 96, 156);
        imagefill($image, 0, 0, $background);

        ob_start();
        imagejpeg($image);
        $contents = ob_get_clean();

        imagedestroy($image);

        return (string) $contents;
    }
}
