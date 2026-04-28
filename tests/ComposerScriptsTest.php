<?php

declare(strict_types=1);

it('defines composer test and test:cov scripts', function () {
    $composer = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['scripts'])->toHaveKey('test');
    expect($composer['scripts'])->toHaveKey('test:cov');
});

it('points the test:cov script at pest with --coverage', function () {
    $composer = json_decode(
        file_get_contents(__DIR__ . '/../composer.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($composer['scripts']['test:cov'])->toContain('pest');
    expect($composer['scripts']['test:cov'])->toContain('--coverage');
});
