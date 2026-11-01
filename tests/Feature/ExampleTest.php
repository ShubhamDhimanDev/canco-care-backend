<?php

test('the home route sends guests to the login page', function () {
    $this->get(route('home'))->assertRedirect('/dashboard');

    $this->followingRedirects()
        ->get(route('home'))
        ->assertOk();
});
