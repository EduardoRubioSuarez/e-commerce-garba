<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYPgPAAEDAQAIicLsAAAAAElFTkSuQmCC';

    private const JPEG = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwDxyiiiv3E8w//Z';

    private const WEBP = 'UklGRjoAAABXRUJQVlA4IC4AAACQAQCdASoBAAEAAUAmJaACdLoAA5gA/vtV4/+lwf/S4P/pcH/pcH8bss4bpAAA';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Storage::fake('public');
    }

    private function image(string $extension = 'png'): UploadedFile
    {
        $encoded = match ($extension) {
            'jpg' => self::JPEG, 'webp' => self::WEBP, default => self::PNG
        };

        return UploadedFile::fake()->createWithContent('original.'.$extension, base64_decode($encoded));
    }

    private function url(Product $product): string
    {
        return '/admin/products/'.$product->id.'/photos';
    }

    #[DataProvider('allowedFormats')]
    public function test_valid_upload_is_saved_with_generated_name(string $extension): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())
            ->post($this->url($product), ['photo' => $this->image($extension), 'alt' => 'Equipo azul', 'sort_order' => 2, 'path' => 'evil.php', 'product_id' => 99999])
            ->assertRedirect(route('admin.products.edit', $product));
        $photo = ProductPhoto::sole();
        $this->assertSame($product->id, $photo->product_id);
        $this->assertSame('Equipo azul', $photo->alt);
        $this->assertSame(2, $photo->sort_order);
        $this->assertMatchesRegularExpression('#^product-photos/'.$product->id.'/[a-f0-9-]{36}\\.'.$extension.'$#', $photo->path);
        Storage::disk('public')->assertExists($photo->path);
        $this->assertSame($product->id, $photo->product->id);
    }

    /** @return array<string, array{string}> */
    public static function allowedFormats(): array
    {
        return ['png' => ['png'], 'jpeg' => ['jpg'], 'webp' => ['webp']];
    }

    public function test_guests_installers_and_suspended_administrators_cannot_upload_or_edit(): void
    {
        $product = Product::factory()->create();
        $photo = ProductPhoto::factory()->create(['product_id' => $product->id]);
        $this->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Foto', 'sort_order' => 0])->assertRedirect('/login');
        foreach ([User::factory()->installer()->create(), User::factory()->administrator()->suspended()->create()] as $user) {
            $this->actingAs($user)->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Foto', 'sort_order' => 0])->assertForbidden();
            $this->actingAs($user)->put($this->url($product).'/'.$photo->id, ['alt' => 'Intruso', 'sort_order' => 4])->assertForbidden();
        }
        $this->assertDatabaseCount('product_photos', 1);
        $this->assertNotSame('Intruso', $photo->fresh()->alt);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('unsafeFormats')]
    public function test_nonimages_and_executable_names_are_rejected(string $filename, string $content): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->post($this->url($product), ['photo' => UploadedFile::fake()->createWithContent($filename, $content), 'alt' => 'Foto', 'sort_order' => 0])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('product_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeFormats(): array
    {
        return ['fake jpeg' => ['fake.jpg', '<?php echo "unsafe";'], 'html' => ['page.html', '<html>unsafe</html>'], 'svg' => ['vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'], 'real png named php' => ['real.php', base64_decode(self::PNG)]];
    }

    public function test_size_and_dimension_limits_are_enforced(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $png = base64_decode(self::PNG);
        $atLimit = UploadedFile::fake()->createWithContent('limit.png', str_pad($png, 5120 * 1024, "\0"));
        $this->post($this->url($product), ['photo' => $atLimit, 'alt' => 'Límite válido', 'sort_order' => 0])->assertRedirect();
        $tooLarge = UploadedFile::fake()->createWithContent('large.png', str_pad($png, 5120 * 1024 + 1, "\0"));
        $this->post($this->url($product), ['photo' => $tooLarge, 'alt' => 'Grande', 'sort_order' => 0])->assertSessionHasErrors('photo');
        $wide = 'iVBORw0KGgoAAAANSUhEUgAAF3EAAAABCAIAAADh8wcOAAAAKklEQVR4nO3BAQ0AAAjAoNs/tPZwwNQGAAAAAAAAAAAAAAAAAAAAAEAfHJtxAQG/XsvbAAAAAElFTkSuQmCC';
        $this->post($this->url($product), ['photo' => UploadedFile::fake()->createWithContent('wide.png', base64_decode($wide)), 'alt' => 'Ancha', 'sort_order' => 0])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('product_photos', 1);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_metadata_edit_preserves_file_and_rejects_cross_product_reference(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $photo = ProductPhoto::factory()->create(['product_id' => $product->id, 'sort_order' => 8]);
        Storage::disk('public')->put($photo->path, base64_decode(self::PNG));
        $this->actingAs(User::factory()->administrator()->create());
        $this->put($this->url($other).'/'.$photo->id, ['alt' => 'Ajeno', 'sort_order' => 0])->assertNotFound();
        $originalPath = $photo->path;
        $this->put($this->url($product).'/'.$photo->id, ['alt' => 'Nueva descripción', 'sort_order' => 1, 'path' => 'evil.php', 'product_id' => $other->id])
            ->assertRedirect(route('admin.products.edit', $product));
        $photo->refresh();
        $this->assertSame('Nueva descripción', $photo->alt);
        $this->assertSame(1, $photo->sort_order);
        $this->assertSame($originalPath, $photo->path);
        $this->assertSame($product->id, $photo->product_id);
        Storage::disk('public')->assertExists($originalPath);
        $this->delete($this->url($product).'/'.$photo->id)->assertStatus(405);
    }

    public function test_database_failure_cleans_only_new_file(): void
    {
        $product = Product::factory()->create();
        $existing = ProductPhoto::factory()->create(['product_id' => $product->id]);
        Storage::disk('public')->put($existing->path, base64_decode(self::PNG));
        ProductPhoto::creating(function (ProductPhoto $photo) use ($existing): void {
            $photo->path = $existing->path;
        });
        $this->actingAs(User::factory()->administrator()->create())->withoutExceptionHandling();
        try {
            $this->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Nueva', 'sort_order' => 0]);
            $this->fail('La restricción única debe rechazar la escritura simulada.');
        } catch (QueryException) {
            $this->assertDatabaseCount('product_photos', 1);
            $this->assertSame([$existing->path], Storage::disk('public')->allFiles());
            Storage::disk('public')->assertExists($existing->path);
        }
    }

    public function test_gallery_renders_ordered_images_alt_and_safe_metadata_forms(): void
    {
        $product = Product::factory()->create();
        $later = ProductPhoto::factory()->create(['product_id' => $product->id, 'alt' => '<script>later</script>', 'sort_order' => 9]);
        $first = ProductPhoto::factory()->create(['product_id' => $product->id, 'alt' => 'Primera fotografía', 'sort_order' => 1]);
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('admin.products.edit', $product))->assertOk()->assertSeeInOrder(['Primera fotografía', '&lt;script&gt;later&lt;/script&gt;'], false)
            ->assertSee(Storage::disk('public')->url($first->path), false)->assertDontSee('<script>later</script>', false)
            ->assertSee('multipart/form-data', false)->assertSee('name="_token"', false);
        $this->assertSame([$first->id, $later->id], $product->photos->pluck('id')->all());
    }

    public function test_cancelled_model_save_cleans_new_file(): void
    {
        $product = Product::factory()->create();
        ProductPhoto::creating(fn (ProductPhoto $photo): bool => false);
        $this->actingAs(User::factory()->administrator()->create())->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        try {
            $this->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Nueva', 'sort_order' => 0]);
        } finally {
            $this->assertDatabaseCount('product_photos', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_partial_failed_filesystem_write_is_cleaned(): void
    {
        $product = Product::factory()->create();
        $realDisk = Storage::disk('public');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (string $directory, UploadedFile $file, string $filename) use ($realDisk): bool {
            $realDisk->put($directory.'/'.$filename, 'partial');

            return false;
        });
        $disk->shouldReceive('delete')->once()->andReturnUsing(fn (string $path): bool => $realDisk->delete($path));
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $this->actingAs(User::factory()->administrator()->create())->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        try {
            $this->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Nueva', 'sort_order' => 0]);
        } finally {
            $this->assertDatabaseCount('product_photos', 0);
            $this->assertSame([], $realDisk->allFiles());
        }
    }

    public function test_cleanup_failure_is_reported_without_database_record(): void
    {
        $product = Product::factory()->create();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        Exceptions::fake();
        $this->actingAs(User::factory()->administrator()->create())
            ->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Nueva', 'sort_order' => 0])->assertStatus(500);
        Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'No se pudo limpiar'));
        $this->assertDatabaseCount('product_photos', 0);
    }

    #[DataProvider('dimensionCases')]
    public function test_both_dimension_boundaries_are_checked(string $image, bool $accepted): void
    {
        $product = Product::factory()->create();
        $response = $this->actingAs(User::factory()->administrator()->create())->post($this->url($product), ['photo' => UploadedFile::fake()->createWithContent('dimensions.png', base64_decode($image)), 'alt' => 'Dimensiones', 'sort_order' => 0]);
        if ($accepted) {
            $response->assertRedirect();
            $this->assertDatabaseCount('product_photos', 1);
        } else {
            $response->assertSessionHasErrors('photo');
            $this->assertDatabaseCount('product_photos', 0);
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function dimensionCases(): array
    {
        return [
            'height exceeded' => ['iVBORw0KGgoAAAANSUhEUgAAAAEAABdxCAIAAAA5LPxfAAAAk0lEQVR4nO3QQQ0AAAgAoZv9O2sPx4MCVDsVOciBgxzkwEEOcuAgBzlwkIMcOMhBDhzkIAcOcpADBznIgYMc5MBBDnLgIAc5cJCDHDjIQQ4c5CAHDnKQAwc5yIGDHOTAQQ5y4CAHOXCQgxw4yEEOHOQgBw5ykAMHOciBgxzkwEEOcuAgBzlwkIMcOMhBDhzkoB8HB+kAL+A8GqkQAAAAAElFTkSuQmCC', false],
            'maximum width' => ['iVBORw0KGgoAAAANSUhEUgAAF3AAAAABCAIAAAAOMWwwAAAAK0lEQVR4nO3BAQ0AAAjAoNs/tOZwA6Y2AAAAAAAAAAAAAAAAAAAAAAB67wCYbgEBjGMhtQAAAABJRU5ErkJggg==', true],
            'maximum height' => ['iVBORw0KGgoAAAANSUhEUgAAAAEAABdwCAIAAADycC/6AAAAk0lEQVR4nO3QQQ0AAAgAoZv9O2sONx4UoNqpyEEOHOQgBw5ykAMHOciBgxzkwEEOcuAgBzlwkIMcOMhBDhzkIAcOcpADBznIgYMc5MBBDnLgIAc5cJCDHDjIQQ4c5CAHDnKQAwc5yIGDHOTAQQ5y4CAHOXCQgxw4yEEOHOQgBw5ykAMHOciBgxzkwEEOcuAgB/0/OCmAL96YR+p5AAAAAElFTkSuQmCC', true],
        ];
    }

    public function test_invalid_metadata_has_no_storage_or_database_effect(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())
            ->post($this->url($product), ['photo' => $this->image(), 'alt' => '', 'sort_order' => -1])->assertSessionHasErrors(['alt', 'sort_order']);
        $this->assertDatabaseCount('product_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $photo = ProductPhoto::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);
        $this->put($this->url($product).'/'.$photo->id, ['alt' => str_repeat('a', 256), 'sort_order' => '1.5'])->assertSessionHasErrors(['alt', 'sort_order']);
        $this->assertSame(1, $photo->fresh()->sort_order);
    }

    public function test_photo_routes_require_real_csrf_validation(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
        $this->app['env'] = 'local';
        try {
            $this->post($this->url($product), ['photo' => $this->image(), 'alt' => 'Nueva', 'sort_order' => 0])->assertStatus(419);
            $this->assertDatabaseCount('product_photos', 0);
            $this->assertSame([], Storage::disk('public')->allFiles());
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
