<?php

namespace Tests\Feature;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSchoolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_schools_are_listed(): void
    {
        School::factory()->create(['name' => 'Approved One']);
        School::factory()->status(SchoolStatus::Pending)->create(['name' => 'Pending One']);
        School::factory()->status(SchoolStatus::Rejected)->create(['name' => 'Rejected One']);
        School::factory()->status(SchoolStatus::Deactivated)->create(['name' => 'Deactivated One']);

        $this->getJson('/api/v1/storefront/schools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Approved One');
    }

    public function test_response_exposes_only_public_fields(): void
    {
        $school = School::factory()->create();

        $this->getJson('/api/v1/storefront/schools')
            ->assertOk()
            ->assertJsonPath('data.0.id', $school->ulid)
            ->assertJsonStructure(['data' => [['id', 'name', 'city', 'state', 'avatar_url']], 'meta', 'links'])
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_search_matches_name_and_city(): void
    {
        School::factory()->create(['name' => 'Lincoln High School', 'city' => 'Los Angeles']);
        School::factory()->create(['name' => 'Oak Ridge Academy', 'city' => 'Nashville']);

        $this->getJson('/api/v1/storefront/schools?search=lincoln')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/storefront/schools?search=nashville')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/storefront/schools?search=nothing-matches')->assertJsonCount(0, 'data');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        School::factory()->create(['name' => 'Lincoln High School']);

        $this->getJson('/api/v1/storefront/schools?search=%25')->assertJsonCount(0, 'data');
    }

    public function test_results_are_paginated(): void
    {
        School::factory()->count(5)->create();

        $this->getJson('/api/v1/storefront/schools?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_per_page_is_capped(): void
    {
        $this->getJson('/api/v1/storefront/schools?per_page=500')->assertStatus(422);
    }

    public function test_show_returns_an_approved_school(): void
    {
        $school = School::factory()->create();

        $this->getJson("/api/v1/storefront/schools/{$school->ulid}")
            ->assertOk()
            ->assertJsonPath('data.id', $school->ulid);
    }

    public function test_show_is_404_once_a_school_is_no_longer_approved(): void
    {
        $school = School::factory()->status(SchoolStatus::Deactivated)->create();

        $this->getJson("/api/v1/storefront/schools/{$school->ulid}")->assertNotFound();
        $this->getJson('/api/v1/storefront/schools/does-not-exist')->assertNotFound();
    }

    public function test_public_responses_are_cacheable(): void
    {
        $this->getJson('/api/v1/storefront/schools')
            ->assertHeader('Cache-Control')
            ->assertHeader('ETag');
    }

    public function test_cors_allows_the_configured_store_origin_only(): void
    {
        config(['cors.allowed_origins' => ['https://my-store.myshopify.com']]);

        $this->getJson('/api/v1/storefront/schools', ['Origin' => 'https://my-store.myshopify.com'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://my-store.myshopify.com');

        // With a single allowed origin the CORS layer always answers with that origin.
        // A browser on any other origin then refuses to read the response, which is what we want.
        $evil = $this->getJson('/api/v1/storefront/schools', ['Origin' => 'https://evil.example']);
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));
    }
}
