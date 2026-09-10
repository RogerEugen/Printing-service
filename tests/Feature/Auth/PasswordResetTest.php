<?php

it('does not expose email password reset routes', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password')->assertNotFound();
    $this->get('/reset-password/token')->assertNotFound();
});
