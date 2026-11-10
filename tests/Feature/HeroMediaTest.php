<?php

namespace Tests\Feature;

use App\Models\RestaurantSetting;
use App\Models\User;
use App\Services\HeroMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HeroMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function image(string $name = 'background.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lZ0AAAAASUVORK5CYII='));
    }

    public function test_public_home_settings_include_default_video_and_image_fallback(): void
    {
        $this->getJson('/api/restaurant-settings')->assertOk()
            ->assertJsonPath('settings.hero_media.type', 'video')
            ->assertJsonPath('settings.hero_media.video_url', '/videos/sushi-intro.mp4');
        $this->assertNotEmpty(app(HeroMedia::class)->current()['image_url']);
    }

    public function test_only_admin_can_publish_background_media(): void
    {
        $payload = ['media_type' => 'image', 'source' => 'existing'];
        $this->postJson('/api/admin/hero-media', $payload)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));
        $this->postJson('/api/admin/hero-media', $payload)->assertForbidden();
        $this->assertDatabaseMissing('restaurant_settings', ['key' => 'hero_media_type']);
    }

    public function test_admin_can_upload_image_replace_it_and_return_to_video(): void
    {
        $this->admin();
        $this->post('/api/admin/hero-media', ['media_type' => 'image', 'source' => 'upload', 'file' => $this->image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('hero_media.type', 'image');
        $first = RestaurantSetting::value('hero_image_path');
        Storage::disk('public')->assertExists($first);
        $this->post('/api/admin/hero-media', ['media_type' => 'image', 'source' => 'upload', 'file' => $this->image('new.png')], ['Accept' => 'application/json'])->assertOk();
        $second = RestaurantSetting::value('hero_image_path');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->postJson('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'existing'])->assertOk()->assertJsonPath('hero_media.type', 'video');
        Storage::disk('public')->assertExists($second);
        $this->getJson('/api/restaurant-settings')->assertJsonPath('settings.hero_media.image_url', Storage::disk('public')->url($second));
    }

    public function test_admin_can_upload_video_and_keep_it_while_switching_to_image(): void
    {
        $this->admin();
        $this->post('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'upload', 'file' => UploadedFile::fake()->create('intro.mp4', 100, 'video/mp4')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('hero_media.type', 'video');
        $path = RestaurantSetting::value('hero_video_path');
        Storage::disk('public')->assertExists($path);
        $this->postJson('/api/admin/hero-media', ['media_type' => 'image', 'source' => 'existing'])->assertOk();
        Storage::disk('public')->assertExists($path);
        $this->postJson('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'existing'])->assertOk()
            ->assertJsonPath('hero_media.video_url', Storage::disk('public')->url($path));
    }

    public function test_invalid_format_and_oversized_upload_do_not_change_live_media(): void
    {
        $this->admin();
        $before = app(HeroMedia::class)->current();
        foreach ([UploadedFile::fake()->create('attack.mp4', 10, 'text/html'), UploadedFile::fake()->create('large.mp4', 30721, 'video/mp4')] as $file) {
            $this->post('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'upload', 'file' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->post('/api/admin/hero-media', ['media_type' => 'image', 'source' => 'upload', 'file' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame($before, app(HeroMedia::class)->current());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_https_direct_media_url_is_allowed_but_youtube_and_wrong_protocol_are_rejected(): void
    {
        $this->admin();
        foreach (['http://example.com/video.mp4', 'https://youtube.com/watch?v=test', 'https://example.com/code.html'] as $url) {
            $this->postJson('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'url', 'url' => $url])->assertUnprocessable()->assertJsonValidationErrors('url');
        }
        $this->postJson('/api/admin/hero-media', ['media_type' => 'video', 'source' => 'url', 'url' => 'https://example.com/intro.mp4'])
            ->assertOk()->assertJsonPath('hero_media.video_url', 'https://example.com/intro.mp4');
    }
}
