<?php

namespace Tests\Unit\Services;

use App\Models\Festival;
use App\Models\Production;
use App\Models\Subscriber;
use App\Services\ProductionMatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMatcherTest extends TestCase
{
    use RefreshDatabase;

    private ProductionMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new ProductionMatcher();
    }

    public function test_service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ProductionMatcher::class, $this->matcher);
    }

    public function test_match_returns_festivals_with_same_category(): void
    {
        $subscriber = Subscriber::factory()->create();

        $matching = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);
        Festival::factory()->create([
            'category' => 'horror',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->contains($matching));
    }

    public function test_match_filters_out_festivals_with_past_deadline(): void
    {
        $subscriber = Subscriber::factory()->create();

        Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('-1 day'),
        ]);
        $future = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->contains($future));
    }

    public function test_match_filters_out_festivals_not_accepting_submissions(): void
    {
        $subscriber = Subscriber::factory()->create();

        Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => false,
            'deadline' => Carbon::parse('+30 days'),
        ]);
        $accepting = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->contains($accepting));
    }

    public function test_match_filters_by_genres_only_when_no_category(): void
    {
        $subscriber = Subscriber::factory()->create();

        $horror = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['horror', 'thriller']],
        ]);
        Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['comedy']],
        ]);

        // Production with NO category but with genres=['horror'] — must
        // match festivals whose details.genres overlap.
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => null,
            'country' => null,
            'genres' => ['horror'],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->contains($horror));
    }

    public function test_category_alone_returns_all_festivals_in_category_ignoring_genre(): void
    {
        $subscriber = Subscriber::factory()->create();

        $f1 = Festival::factory()->create([
            'category' => 'documentary',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['documentary']],
        ]);
        $f2 = Festival::factory()->create([
            'category' => 'documentary',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['drama']], // Different genre, but same category.
        ]);

        // Production: category=documentary, genres=['drama'] (user-typed).
        // Both festivals MUST match because they share the category —
        // genres no longer gate the result when category is set.
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'documentary',
            'country' => null,
            'genres' => ['drama'],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(2, $matches);
        $this->assertTrue($matches->contains($f1));
        $this->assertTrue($matches->contains($f2));
    }

    public function test_match_escapes_like_wildcards_in_genre(): void
    {
        $subscriber = Subscriber::factory()->create();

        // Festival whose details->genres JSON contains the literal string "%"
        $fake = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['%horror']],
        ]);
        // A festival with a real genre that the wildcard would otherwise match
        $real = Festival::factory()->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
            'details' => ['genres' => ['drama']],
        ]);

        // Production has NO category — so genres DO act as the filter,
        // and escaping matters here.
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => null,
            'country' => null,
            'genres' => ['horror'],
        ]);

        $matches = $this->matcher->matchFor($production);

        // Without escaping, '%horror' would act as a wildcard and the real
        // drama festival would match. Escaping means only 'horror' is searched.
        $this->assertFalse($matches->contains($real));
        $this->assertCount(0, $matches->filter(fn ($f) => $f->id === $real->id));
    }

    public function test_match_escapes_like_wildcards_in_country(): void
    {
        $subscriber = Subscriber::factory()->create();

        $mx = Festival::factory()->create([
            'category' => 'short_film',
            'country' => 'Mexico',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);
        $us = Festival::factory()->create([
            'category' => 'short_film',
            'country' => 'United States',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        // Production's country has a wildcard underscore "_" — should not match
        // any country other than literals
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'M_xico',
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        // "M_xico" escaped becomes "M\_xico" — no literal country contains that
        $this->assertCount(0, $matches);
    }

    public function test_match_returns_empty_when_production_has_no_matchable_attrs(): void
    {
        $subscriber = Subscriber::factory()->create();

        Festival::factory()->count(3)->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        // Production with no filters should NOT return all festivals
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => null,
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(0, $matches);
    }

    public function test_match_respects_limit_50(): void
    {
        $subscriber = Subscriber::factory()->create();

        Festival::factory()->count(80)->create([
            'category' => 'short_film',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => null,
            'genres' => [],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(50, $matches);
    }

    public function test_match_combines_category_and_country_filters_with_and(): void
    {
        $subscriber = Subscriber::factory()->create();

        // category=short_film + country=Mexico => match
        $full = Festival::factory()->create([
            'category' => 'short_film',
            'country' => 'Mexico',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);
        // category=short_film but country=Spain => NO match (country fails)
        Festival::factory()->create([
            'category' => 'short_film',
            'country' => 'Spain',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);
        // category=feature but country=Mexico => NO match (category fails)
        Festival::factory()->create([
            'category' => 'feature',
            'country' => 'Mexico',
            'accepting_submissions' => true,
            'deadline' => Carbon::parse('+30 days'),
        ]);

        // Production has category+country. Genres are NOT in the filter
        // because category is set; they're just metadata.
        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Mexico',
            'genres' => ['horror'],
        ]);

        $matches = $this->matcher->matchFor($production);

        $this->assertCount(1, $matches);
        $this->assertTrue($matches->contains($full));
    }
}