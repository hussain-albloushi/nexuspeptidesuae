<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->role = 'admin';
        $user->save();

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_can_load_the_content_editor(): void
    {
        $this->admin();

        $response = $this->getJson('/api/content-editor');

        $response->assertOk()
            ->assertJsonCount(count(config('storefront-content.groups')), 'groups')
            ->assertJsonPath('groups.0.key', 'site')
            ->assertJsonPath('groups.0.sections.0.fields.0.name', 'name');
    }

    public function test_saving_a_group_publishes_it_to_the_storefront(): void
    {
        $this->admin();

        $this->putJson('/api/content-editor/site', [
            'data' => [
                'name' => 'Acme Research',
                'seoTitle' => 'Acme Research Peptides',
                'seoDescription' => 'A test description.',
            ],
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Acme Research');

        $this->assertDatabaseHas('contents', [
            'key' => 'site',
            'type' => 'group',
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Acme Research Peptides')
            ->assertSee('Acme Research');
    }

    public function test_unpublishing_a_group_removes_it_from_the_injected_content(): void
    {
        $this->admin();

        Content::create([
            'key' => 'site',
            'label' => 'Site & Footer',
            'type' => 'group',
            'data' => ['name' => 'Hidden Brand'],
            'is_active' => true,
        ]);

        $this->putJson('/api/content-editor/site', [
            'data' => ['name' => 'Hidden Brand'],
            'is_active' => false,
        ])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Hidden Brand');
    }

    public function test_invalid_group_keys_are_rejected(): void
    {
        $this->admin();

        $this->putJson('/api/content-editor/does-not-exist', [
            'data' => [],
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_customers_cannot_manage_content(): void
    {
        $user = User::factory()->create();
        $user->role = 'customer';
        $user->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/content-editor')->assertForbidden();
    }

    public function test_guests_are_redirected_from_the_content_api(): void
    {
        $this->getJson('/api/content-editor')->assertUnauthorized();
    }
}
