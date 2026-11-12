<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_category_names_and_combines_price_stock_and_status_filters(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Research Essentials', 'slug' => 'research-essentials']);
        $target = $this->product(['category_id' => $category->id]);
        $this->product(['sku' => 'OTHER', 'slug' => 'other', 'category_id' => $category->id, 'is_active' => false]);
        $this->product(['sku' => 'HIGH', 'slug' => 'high', 'category_id' => $category->id, 'price' => 300]);

        $this->getJson('/api/products?'.http_build_query([
            'q' => 'RESEARCH essentials', 'category_id' => $category->id,
            'min_price' => 100, 'max_price' => 150, 'stock_status' => 'low_stock', 'is_active' => 1, 'per_page' => 10,
        ]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $target->id);
    }

    public function test_product_listing_is_paginated_in_stable_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        for ($index = 1; $index <= 12; $index++) {
            $this->product(['sku' => 'SKU-'.$index, 'slug' => 'product-'.$index]);
        }

        $this->getJson('/api/products?per_page=10&page=2')->assertOk()
            ->assertJsonPath('current_page', 2)->assertJsonPath('total', 12)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sku', 'SKU-2')->assertJsonPath('data.1.sku', 'SKU-1');
    }

    #[DataProvider('stockStatuses')]
    public function test_stock_filters_use_quantities_and_thresholds(string $status, int $quantity): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([0, 5, 6] as $stock) {
            $this->product(['sku' => 'S-'.$stock, 'slug' => 's-'.$stock, 'stock_quantity' => $stock, 'stock_status' => 'in_stock']);
        }

        $this->getJson('/api/products?per_page=10&stock_status='.$status)->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.stock_quantity', $quantity);
    }

    public static function stockStatuses(): array
    {
        return ['unavailable' => ['out_of_stock', 0], 'at threshold' => ['low_stock', 5], 'healthy' => ['in_stock', 6]];
    }

    public function test_read_only_details_include_variants_images_and_metadata(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['description' => 'Product information', 'seo_title' => 'Research product']);
        $product->variants()->create(['name' => 'Small', 'sku' => 'SMALL', 'price_modifier' => 10, 'stock_quantity' => 2]);
        $product->images()->create(['image_path' => '/images/product.jpg', 'is_primary' => true]);

        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('description', 'Product information')->assertJsonPath('seo_title', 'Research product')
            ->assertJsonPath('variants.0.sku', 'SMALL')->assertJsonPath('images.0.image_path', '/images/product.jpg');
    }

    public function test_product_updates_are_validated_and_stock_changes_are_recorded(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();

        $this->putJson('/api/products/'.$product->id, $this->payload($product, [
            'name' => 'Updated product', 'price' => '125.50', 'stock_quantity' => 20, 'is_active' => false,
        ]))->assertOk()->assertJsonPath('product.name', 'Updated product')->assertJsonPath('product.stock_status', 'in_stock');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated product', 'price' => 125.5, 'stock_quantity' => 20, 'is_active' => false]);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id, 'quantity_before' => 5, 'quantity_after' => 20, 'type' => 'adjustment']);
    }

    public function test_unchanged_stock_does_not_create_an_adjustment_and_threshold_recalculates_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();

        $this->putJson('/api/products/'.$product->id, $this->payload($product, ['low_stock_threshold' => 1]))
            ->assertOk()->assertJsonPath('product.stock_status', 'in_stock');

        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_existing_local_image_paths_can_be_saved(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['featured_image_url' => '/catalog-images/product.webp']);

        $this->putJson('/api/products/'.$product->id, $this->payload($product, [
            'featured_image_url' => '/catalog-images/product.webp',
        ]))->assertOk()->assertJsonPath('product.featured_image_url', '/catalog-images/product.webp');
    }

    public function test_an_admin_can_upload_and_replace_the_product_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['featured_image_url' => '/catalog-images/old.webp']);
        $image = $this->imageUpload();

        $this->post('/api/products/'.$product->id, $this->payload($product, [
            '_method' => 'PUT', 'image' => $image,
        ]), ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('product.featured_image_url', '/storage/products/'.$product->id.'/'.$image->hashName());

        Storage::disk('public')->assertExists('products/'.$product->id.'/'.$image->hashName());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'featured_image_url' => '/storage/products/'.$product->id.'/'.$image->hashName()]);
    }

    public function test_an_admin_can_remove_the_product_image(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['featured_image_url' => '/catalog-images/old.webp']);

        $this->putJson('/api/products/'.$product->id, $this->payload($product, ['featured_image_url' => null]))
            ->assertOk()->assertJsonPath('product.featured_image_url', null);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'featured_image_url' => null]);
    }

    public function test_invalid_or_oversized_images_return_422_and_keep_the_existing_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['featured_image_url' => '/catalog-images/old.webp']);

        foreach ([UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'), $this->imageUpload()->size(2049)] as $image) {
            $this->post('/api/products/'.$product->id, $this->payload($product, [
                '_method' => 'PUT', 'image' => $image,
            ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'featured_image_url' => '/catalog-images/old.webp']);
        Storage::disk('public')->assertEmpty();
    }

    public function test_rejected_stock_changes_do_not_store_the_selected_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product(['featured_image_url' => '/catalog-images/old.webp']);

        $this->post('/api/products/'.$product->id, $this->payload($product, [
            '_method' => 'PUT', 'expected_stock_quantity' => 99, 'image' => $this->imageUpload(),
        ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

        Storage::disk('public')->assertEmpty();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'featured_image_url' => '/catalog-images/old.webp']);
    }

    public function test_concurrent_stock_changes_return_422_without_overwriting_product_data(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $payload = $this->payload($product, ['name' => 'Stale update', 'stock_quantity' => 10]);
        $product->update(['stock_quantity' => 4]);

        $this->putJson('/api/products/'.$product->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Research product', 'stock_quantity' => 4]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_product_fields_return_422_and_do_not_update(string $field, mixed $value): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();

        $this->putJson('/api/products/'.$product->id, $this->payload($product, [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Research product', 'price' => 100, 'stock_quantity' => 5]);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public static function invalidFields(): array
    {
        return [
            'blank name' => ['name', ''], 'blank sku' => ['sku', ''], 'invalid slug' => ['slug', 'UPPER CASE'],
            'negative price' => ['price', -1], 'money precision' => ['price', 1.234],
            'compare price too low' => ['compare_at_price', 20], 'negative cost' => ['cost_price', -1],
            'negative stock' => ['stock_quantity', -1], 'fractional stock' => ['stock_quantity', 2.5],
            'negative threshold' => ['low_stock_threshold', -1], 'missing category' => ['category_id', 999],
            'invalid status' => ['is_active', 'sometimes'], 'invalid visibility' => ['visibility', 'anywhere'],
            'unsafe image URL' => ['featured_image_url', 'javascript:alert(1)'],
            'long seo title' => ['seo_title', str_repeat('a', 256)],
        ];
    }

    public function test_duplicate_sku_or_slug_returns_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $this->product(['sku' => 'TAKEN', 'slug' => 'taken']);

        $this->putJson('/api/products/'.$product->id, $this->payload($product, ['sku' => 'TAKEN', 'slug' => 'taken']))
            ->assertUnprocessable()->assertJsonValidationErrors(['sku', 'slug']);
    }

    public function test_deleting_a_product_hides_it_and_preserves_its_history(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        InventoryTransaction::create(['product_id' => $product->id, 'quantity_before' => 0, 'quantity_after' => 5, 'type' => 'restock']);

        $this->deleteJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('inventory_transactions', ['product_id' => $product->id]);
        $this->getJson('/api/products?per_page=10')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
    }

    public function test_invalid_filters_return_422(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/products?per_page=10000&page=0&stock_status=unknown&min_price=50&max_price=10')
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page', 'stock_status', 'max_price']);
    }

    public function test_non_admins_cannot_read_change_or_delete_products(): void
    {
        $product = $this->product();
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/products/'.$product->id)->assertUnauthorized();
        $this->putJson('/api/products/'.$product->id, [])->assertUnauthorized();
        $this->deleteJson('/api/products/'.$product->id)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products')->assertForbidden();
        $this->getJson('/api/products/'.$product->id)->assertForbidden();
        $this->putJson('/api/products/'.$product->id, [])->assertForbidden();
        $this->deleteJson('/api/products/'.$product->id)->assertForbidden();
        $this->assertNotSoftDeleted($product);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Research product', 'sku' => 'NX-100', 'slug' => 'research-product', 'price' => 100,
            'stock_quantity' => 5, 'low_stock_threshold' => 5, 'is_active' => true,
        ], $attributes));
    }

    private function imageUpload(): File
    {
        return UploadedFile::fake()->createWithContent('image.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    private function payload(Product $product, array $attributes = []): array
    {
        return array_merge([
            'name' => $product->name, 'sku' => $product->sku, 'slug' => $product->slug,
            'price' => $product->price, 'stock_quantity' => $product->stock_quantity,
            'expected_stock_quantity' => $product->stock_quantity, 'low_stock_threshold' => $product->low_stock_threshold,
            'is_active' => true, 'featured' => false, 'requires_subscription' => false, 'visibility' => 'public',
        ], $attributes);
    }
}
