<?php

namespace App\Services;

use App\Interfaces\SearchInterface;

class SearchService
{
    protected $searchInterface;

    public function __construct(SearchInterface $searchInterface)
    {
        $this->searchInterface = $searchInterface;
    }

    /**
     * Perform search for authors and courses based on query.
     */
    public function search(string $query): array
    {

        $authors = $this->searchInterface->searchAuthors($query);
        $courses = $this->searchInterface->searchCourses($query);

        return [
            'authors' => $authors,
            'courses' => $courses,
        ];
    }
}