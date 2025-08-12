<?php

it('redirects home to generate route', function () {
    $response = $this->get('/');

    $response->assertRedirect('/generate');
});
