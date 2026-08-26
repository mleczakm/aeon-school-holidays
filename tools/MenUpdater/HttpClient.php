<?php

declare(strict_types=1);

namespace Mleczakm\AeonSchoolHolidays\MenUpdater;

interface HttpClient
{
    public function get(string $url): string;
}
