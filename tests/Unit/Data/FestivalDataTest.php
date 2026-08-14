<?php

namespace Tests\Unit\Data;

use App\Data\FestivalData;
use PHPUnit\Framework\TestCase;

class FestivalDataTest extends TestCase
{
    public function test_best_url_prefers_submission_url_when_present(): void
    {
        // When submission_url is set and non-empty, prefer it over the
        // FilmFreeway search fallback. Even if it's pointing to another
        // festival's slug (the list endpoint mis-mapping), we still link
        // to it — the DTO coming out of FestivalSearchService::details()
        // will have the trustworthy one. This test just documents that
        // submission_url wins when present.
        $festival = new FestivalData(
            apiId: 1,
            name: 'Almeria International Film Festival',
            categories: ['short_film'],
            primaryCategory: 'short_film',
            country: 'Spain',
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: 'https://filmfreeway.com/AlmeriaWesternFilmFestival',
            website: null,
            compositeScore: null,
        );

        $this->assertSame(
            'https://filmfreeway.com/AlmeriaWesternFilmFestival',
            $festival->bestUrl()
        );
    }

    public function test_best_url_falls_back_to_website_when_no_submission_url(): void
    {
        $festival = new FestivalData(
            apiId: 1,
            name: 'Sitges',
            categories: [],
            primaryCategory: null,
            country: null,
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: null,
            website: 'https://sitgesfilmfestival.com',
            compositeScore: null,
        );

        $this->assertSame('https://sitgesfilmfestival.com', $festival->bestUrl());
    }

    public function test_best_url_falls_back_to_filmfreeway_search_when_no_urls(): void
    {
        // Last resort: when neither submission_url nor website is set
        // (the list-endpoint case where those fields are null/empty),
        // build a FilmFreeway search URL from the festival name. This is
        // the scenario where the user would otherwise see "Private Project"
        // because the API didn't return any URL at all.
        $festival = new FestivalData(
            apiId: 1,
            name: 'Almeria International Film Festival',
            categories: ['short_film'],
            primaryCategory: 'short_film',
            country: 'Spain',
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: null,
            website: null,
            compositeScore: null,
        );

        $this->assertSame(
            'https://filmfreeway.com/search?q=Almeria%20International%20Film%20Festival',
            $festival->bestUrl()
        );
    }

    public function test_best_url_returns_null_when_name_is_empty(): void
    {
        $festival = new FestivalData(
            apiId: 1,
            name: '',
            categories: [],
            primaryCategory: null,
            country: null,
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: null,
            website: null,
            compositeScore: null,
        );

        $this->assertNull($festival->bestUrl());
    }

    public function test_best_url_url_encodes_special_characters_in_name(): void
    {
        $festival = new FestivalData(
            apiId: 1,
            name: 'Festival & Co: Edición 2026',
            categories: [],
            primaryCategory: null,
            country: null,
            city: null,
            state: null,
            genres: [],
            deadline: null,
            eventStartDate: null,
            regularFee: null,
            submissionUrl: null,
            website: null,
            compositeScore: null,
        );

        $url = $festival->bestUrl();
        $this->assertNotNull($url);
        // & must be encoded as %26 and : as %3A.
        $this->assertStringContainsString('Festival', $url);
        $this->assertStringContainsString('%26', $url);
        $this->assertStringContainsString('%3A', $url);
    }

    public function test_from_api_accepts_missing_or_empty_optional_fields(): void
    {
        // FestivalAPI often returns empty strings instead of null for
        // missing optional fields. fromApi() preserves those empty
        // strings; bestUrl() is what treats them as falsy and falls
        // through.
        $festival = FestivalData::fromApi([
            'id' => 1,
            'name' => 'Test',
            'submission_url' => '',
            'website' => '',
            'deadline_regular' => '',
            'event_start_date' => '',
        ]);

        $this->assertSame('Test', $festival->name);
        $this->assertSame('', $festival->submissionUrl);
        $this->assertSame('', $festival->website);
        $this->assertNull($festival->deadline); // parseDate returns null on ''
        $this->assertNull($festival->eventStartDate);
    }
}
