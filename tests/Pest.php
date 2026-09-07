<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

expect()->extend('toBeOne', fn () => $this->toBe(1));
