<?php

it('redirects guests from root to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
