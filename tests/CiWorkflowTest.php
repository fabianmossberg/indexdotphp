<?php

declare(strict_types=1);

it('defines a tests CI workflow that runs composer test:cov on a PHP matrix', function () {
    $path = __DIR__ . '/../.github/workflows/tests.yml';

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);
    expect($content)->toContain('composer test:cov');
    expect($content)->toContain('shivammathur/setup-php');
    expect($content)->toContain('pull_request');
});
