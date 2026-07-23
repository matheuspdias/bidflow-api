<?php

test('the docs page renders and references the vendored Redoc bundle', function () {
    $response = $this->get('/docs');

    $response->assertStatus(200);

    // response()->file() returns a BinaryFileResponse — its content is
    // streamed, not set on the response object, so assertSee() (which
    // reads getContent()) sees nothing. streamedContent() captures the
    // actual bytes, same as the openapi.yaml test below.
    $content = $response->streamedContent();
    expect($content)->toContain('/vendor/redoc/redoc.standalone.js')
        ->and($content)->toContain('/docs/openapi.yaml');
});

test('the openapi spec is served with the right content type and matches the file on disk', function () {
    $response = $this->get('/docs/openapi.yaml');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/yaml');
    expect($response->streamedContent())->toBe(file_get_contents(base_path('docs/openapi.yaml')));
});
